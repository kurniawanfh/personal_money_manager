import 'package:flutter_test/flutter_test.dart';
import 'package:personal_money_manager/core/network/api_client.dart';
import 'package:personal_money_manager/core/sync/sync_manager.dart';

void main() {
  group('SyncManager Unit Tests', () {
    late ApiClient mockApiClient;
    late SyncManager syncManager;

    setUp(() {
      mockApiClient = ApiClient(token: 'test_token');
      syncManager = SyncManager(apiClient: mockApiClient);
    });

    test('enqueue mutation creates pending sync mutation', () {
      final mutation = syncManager.enqueue(
        entity: 'transactions',
        action: 'create',
        baseRevision: 1,
        payload: {'amount': 50000, 'type': 'expense'},
      );

      expect(mutation.id, isNotEmpty);
      expect(mutation.entity, equals('transactions'));
      expect(mutation.status, equals(SyncStatus.pending));
      expect(syncManager.pendingMutations.length, equals(1));
    });

    test('exponential backoff delay calculation', () {
      expect(syncManager.getBackoffDelay(0).inSeconds, equals(1));
      expect(syncManager.getBackoffDelay(1).inSeconds, equals(2));
      expect(syncManager.getBackoffDelay(2).inSeconds, equals(4));
      expect(syncManager.getBackoffDelay(3).inSeconds, equals(8));
      expect(syncManager.getBackoffDelay(6).inSeconds, equals(60)); // capped at 60s
    });
  });
}
