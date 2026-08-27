import 'package:flutter/material.dart';

class VoiceBottomSheet extends StatefulWidget {
  final Future<Map<String, dynamic>> Function(String rawText)? onParseVoice;
  final Future<void> Function(Map<String, dynamic> parsedData)? onConfirmTransaction;
  final int quotaRemaining;
  final bool isPremium;

  const VoiceBottomSheet({
    super.key,
    this.onParseVoice,
    this.onConfirmTransaction,
    this.quotaRemaining = 10,
    this.isPremium = false,
  });

  @override
  State<VoiceBottomSheet> createState() => _VoiceBottomSheetState();
}

class _VoiceBottomSheetState extends State<VoiceBottomSheet> {
  bool _isListening = false;
  String _spokenText = '';
  Map<String, dynamic>? _parsedResult;
  bool _isLoading = false;
  String? _errorMessage;

  void _toggleListen() async {
    if (!_isListening) {
      setState(() {
        _isListening = true;
        _spokenText = 'Beli kopi 25k pakai gopay kemarin'; // Simulated speech-to-text input
      });
      // Automatically trigger parse
      _handleParse();
    } else {
      setState(() => _isListening = false);
    }
  }

  void _handleParse() async {
    if (_spokenText.isEmpty) return;

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      if (widget.onParseVoice != null) {
        final res = await widget.onParseVoice!(_spokenText);
        setState(() => _parsedResult = res);
      } else {
        // Fallback local mock parse preview
        setState(() {
          _parsedResult = {
            'intent': 'expense',
            'amount': 25000.0,
            'currency': 'IDR',
            'description': 'Beli kopi',
            'wallet_name': 'GoPay',
            'category_name': 'Food & Beverage',
            'date': '2026-08-27',
            'confidence': 0.95,
          };
        });
      }
    } catch (e) {
      setState(() => _errorMessage = e.toString());
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  void _handleConfirm() async {
    if (_parsedResult == null) return;
    setState(() => _isLoading = true);
    try {
      if (widget.onConfirmTransaction != null) {
        await widget.onConfirmTransaction!(_parsedResult!);
      }
      if (mounted) Navigator.pop(context);
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
        top: 24,
        left: 20,
        right: 20,
        bottom: MediaQuery.of(context).viewInsets.bottom + 24,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            'Voice Expense Logger',
            style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 6),
          Text(
            widget.isPremium ? 'Unlimited Voice Logs (Premium)' : 'Voice Logs Remaining: ${widget.quotaRemaining} / 10',
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.bold,
              color: widget.isPremium ? Colors.amber[800] : (widget.quotaRemaining > 2 ? Colors.blue : Colors.red),
            ),
          ),
          const SizedBox(height: 20),
          GestureDetector(
            onTap: _toggleListen,
            child: CircleAvatar(
              radius: 40,
              backgroundColor: _isListening ? Colors.red.shade100 : Colors.purple.shade50,
              child: Icon(
                _isListening ? Icons.mic : Icons.mic_none,
                size: 38,
                color: _isListening ? Colors.red : Colors.purple,
              ),
            ),
          ),
          const SizedBox(height: 12),
          Text(
            _isListening ? 'Listening... Speak your transaction' : 'Tap mic to start speaking',
            style: TextStyle(color: Colors.grey[600], fontSize: 13),
          ),
          if (_spokenText.isNotEmpty) ...[
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: Colors.grey.shade100, borderRadius: BorderRadius.circular(8)),
              child: Text('“$_spokenText”', style: const TextStyle(fontStyle: FontStyle.italic)),
            ),
          ],
          if (_errorMessage != null) ...[
            const SizedBox(height: 12),
            Text(_errorMessage!, style: const TextStyle(color: Colors.red, fontSize: 12)),
          ],
          if (_parsedResult != null) ...[
            const SizedBox(height: 16),
            Card(
              color: Colors.indigo.shade50,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              child: Padding(
                padding: const EdgeInsets.all(14.0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text('Parsed Review', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.indigo.shade900)),
                        Text('Confidence: ${((_parsedResult!['confidence'] ?? 0.9) * 100).toInt()}%', style: const TextStyle(fontSize: 11)),
                      ],
                    ),
                    const Divider(height: 16),
                    Text('Intent: ${(_parsedResult!['intent'] ?? 'expense').toString().toUpperCase()}'),
                    Text('Amount: ${_parsedResult!['currency']} ${_parsedResult!['amount']}'),
                    Text('Description: ${_parsedResult!['description']}'),
                    Text('Category: ${_parsedResult!['category_name'] ?? 'Auto-detect'}'),
                    Text('Wallet: ${_parsedResult!['wallet_name'] ?? 'Default'}'),
                    Text('Date: ${_parsedResult!['date']}'),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: _isLoading ? null : _handleConfirm,
              style: ElevatedButton.styleFrom(
                minimumSize: const Size.fromHeight(48),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
              child: const Text('Confirm & Save to Ledger'),
            ),
          ],
        ],
      ),
    );
  }
}
