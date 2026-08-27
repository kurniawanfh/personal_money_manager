import 'package:flutter/material.dart';

class TransferModal extends StatefulWidget {
  final List<Map<String, dynamic>> wallets;
  final Future<void> Function(String sourceId, String targetId, double amount, String? notes)? onTransfer;

  const TransferModal({super.key, required this.wallets, this.onTransfer});

  @override
  State<TransferModal> createState() => _TransferModalState();
}

class _TransferModalState extends State<TransferModal> {
  String? _sourceWalletId;
  String? _targetWalletId;
  final _amountController = TextEditingController();
  final _notesController = TextEditingController();
  bool _isLoading = false;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    if (widget.wallets.isNotEmpty) {
      _sourceWalletId = widget.wallets.first['id'];
      if (widget.wallets.length > 1) {
        _targetWalletId = widget.wallets[1]['id'];
      }
    }
  }

  void _handleTransfer() async {
    final amount = double.tryParse(_amountController.text.replaceAll(RegExp(r'[^0-9.]'), ''));

    if (_sourceWalletId == null || _targetWalletId == null) {
      setState(() => _errorMessage = 'Please select source and target wallets');
      return;
    }

    if (_sourceWalletId == _targetWalletId) {
      setState(() => _errorMessage = 'Source and destination wallets must be different');
      return;
    }

    if (amount == null || amount <= 0) {
      setState(() => _errorMessage = 'Please enter a valid transfer amount');
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      if (widget.onTransfer != null) {
        await widget.onTransfer!(_sourceWalletId!, _targetWalletId!, amount, _notesController.text.trim());
      }
      if (mounted) Navigator.pop(context);
    } catch (e) {
      setState(() => _errorMessage = e.toString());
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
        top: 20,
        left: 20,
        right: 20,
        bottom: MediaQuery.of(context).viewInsets.bottom + 20,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'Transfer Between Wallets',
            style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 16),
          if (_errorMessage != null)
            Container(
              padding: const EdgeInsets.all(10),
              margin: const EdgeInsets.only(bottom: 12),
              decoration: BoxDecoration(color: Colors.red[50], borderRadius: BorderRadius.circular(8)),
              child: Text(_errorMessage!, style: const TextStyle(color: Colors.red)),
            ),
          DropdownButtonFormField<String>(
            value: _sourceWalletId,
            decoration: const InputDecoration(labelText: 'From Wallet (Source)', border: OutlineInputBorder()),
            items: widget.wallets.map((w) {
              return DropdownMenuItem<String>(
                value: w['id'],
                child: Text('${w['name']} (${w['currency']} ${w['balance']})'),
              );
            }).toList(),
            onChanged: (val) => setState(() => _sourceWalletId = val),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            value: _targetWalletId,
            decoration: const InputDecoration(labelText: 'To Wallet (Destination)', border: OutlineInputBorder()),
            items: widget.wallets.map((w) {
              return DropdownMenuItem<String>(
                value: w['id'],
                child: Text('${w['name']} (${w['currency']} ${w['balance']})'),
              );
            }).toList(),
            onChanged: (val) => setState(() => _targetWalletId = val),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _amountController,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(
              labelText: 'Transfer Amount',
              prefixText: 'Rp ',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _notesController,
            decoration: const InputDecoration(labelText: 'Notes (Optional)', border: OutlineInputBorder()),
          ),
          const SizedBox(height: 20),
          ElevatedButton(
            onPressed: _isLoading ? null : _handleTransfer,
            style: ElevatedButton.styleFrom(
              padding: const EdgeInsets.symmetric(vertical: 14),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
            ),
            child: _isLoading
                ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                : const Text('Execute Transfer', style: TextStyle(fontSize: 16)),
          ),
        ],
      ),
    );
  }
}
