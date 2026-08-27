import 'dart:async';
import 'dart:math';
import 'package:uuid/uuid.dart';
import '../network/api_client.dart';

enum SyncStatus { pending, inProgress, synced, failedConflict, error }

class SyncMutation {
  final String id;
  final String entity;
  final String action;
  final int baseRevision;
  final Map<String, dynamic> payload;
  final DateTime clientTimestamp;
  SyncStatus status;
  int retryCount;
  String? errorMessage;

  SyncMutation({
    required this.id,
    required this.entity,
    required this.action,
    required this.baseRevision,
    required this.payload,
    required this.clientTimestamp,
    this.status = SyncStatus.pending,
    this.retryCount = 0,
    this.errorMessage,
  });

  Map<String, dynamic> toJson() => {
        'id': id,
        'entity': entity,
        'action': action,
        'base_revision': baseRevision,
        'payload': payload,
        'client_timestamp': clientTimestamp.toIso8601String(),
      };
}

class SyncManager {
  final ApiClient apiClient;
  final List<SyncMutation> _inMemoryQueue = [];
  DateTime? _lastPulledAt;
  bool _isSyncing = false;

  SyncManager({required this.apiClient});

  List<SyncMutation> get pendingMutations =>
      _inMemoryQueue.where((m) => m.status == SyncStatus.pending).toList();

  DateTime? get lastPulledAt => _lastPulledAt;

  /// Enqueue a local mutation for offline-first sync.
  SyncMutation enqueue({
    required String entity,
    required String action,
    required int baseRevision,
    required Map<String, dynamic> payload,
    String? id,
  }) {
    final mutation = SyncMutation(
      id: id ?? const Uuid().v4(),
      entity: entity,
      action: action,
      baseRevision: baseRevision,
      payload: payload,
      clientTimestamp: DateTime.now().toUtc(),
    );

    _inMemoryQueue.add(mutation);
    return mutation;
  }

  /// Flush pending mutations to server with retry backoff and conflict handling.
  Future<Map<String, dynamic>> flushQueue() async {
    if (_isSyncing) return {'status': 'in_progress'};
    final pending = pendingMutations;
    if (pending.isEmpty) return {'status': 'empty', 'processed': 0};

    _isSyncing = true;

    try {
      final payload = {
        'mutations': pending.map((m) => m.toJson()).toList(),
      };

      final response = await apiClient.post('/sync/batch', data: payload);

      if (response.statusCode == 200 && response.data['status'] == 'success') {
        final List results = response.data['data']['results'] ?? [];

        for (final res in results) {
          final String resId = res['id'];
          final String status = res['status'];
          final mutation = _inMemoryQueue.firstWhere((m) => m.id == resId, orElse: () => pending.first);

          if (status == 'synced') {
            mutation.status = SyncStatus.synced;
          } else if (status == 'failed_conflict') {
            mutation.status = SyncStatus.failedConflict;
            mutation.errorMessage = 'Conflict detected with server revision';
          } else {
            mutation.status = SyncStatus.error;
            mutation.errorMessage = res['message'] ?? 'Unknown sync error';
            mutation.retryCount += 1;
          }
        }

        _lastPulledAt = DateTime.parse(response.data['data']['server_time'] ?? DateTime.now().toIso8601String());

        return {
          'status': 'success',
          'processed': pending.length,
          'results': results,
        };
      } else {
        _applyBackoff(pending);
        return {'status': 'error', 'message': 'Non-200 sync response'};
      }
    } catch (e) {
      _applyBackoff(pending, error: e.toString());
      return {'status': 'error', 'error': e.toString()};
    } finally {
      _isSyncing = false;
    }
  }

  /// Pull incremental changes from backend.
  Future<Map<String, dynamic>> pullChanges() async {
    try {
      final queryParams = _lastPulledAt != null
          ? {'last_pulled_at': _lastPulledAt!.toIso8601String()}
          : <String, dynamic>{};

      final response = await apiClient.get('/sync/pull', queryParameters: queryParams);

      if (response.statusCode == 200 && response.data['status'] == 'success') {
        final data = response.data['data'];
        if (data['server_time'] != null) {
          _lastPulledAt = DateTime.parse(data['server_time']);
        }
        return data;
      }
      return {};
    } catch (e) {
      return {'error': e.toString()};
    }
  }

  void _applyBackoff(List<SyncMutation> mutations, {String? error}) {
    for (final m in mutations) {
      m.retryCount += 1;
      m.status = SyncStatus.pending;
      m.errorMessage = error;
    }
  }

  /// Calculate exponential backoff duration: 2^n * 1s (capped at 60s).
  Duration getBackoffDelay(int retryCount) {
    final seconds = min(60, pow(2, retryCount).toInt());
    return Duration(seconds: seconds);
  }
}
