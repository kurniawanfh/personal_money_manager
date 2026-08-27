import 'package:flutter/material.dart';

class TransactionFormScreen extends StatefulWidget {
  final List<Map<String, dynamic>> wallets;
  final List<Map<String, dynamic>> categories;
  final Future<void> Function(Map<String, dynamic> data)? onSave;

  const TransactionFormScreen({
    super.key,
    this.wallets = const [],
    this.categories = const [],
    this.onSave,
  });

  @override
  State<TransactionFormScreen> createState() => _TransactionFormScreenState();
}

class _TransactionFormScreenState extends State<TransactionFormScreen> {
  String _type = 'expense';
  String? _selectedWalletId;
  String? _selectedCategoryId;
  final _amountController = TextEditingController();
  final _descriptionController = TextEditingController();
  final _notesController = TextEditingController();
  DateTime _selectedDate = DateTime.now();
  bool _isLoading = false;

  @override
  void initState() {
    super.initState();
    if (widget.wallets.isNotEmpty) _selectedWalletId = widget.wallets.first['id'];
    if (widget.categories.isNotEmpty) _selectedCategoryId = widget.categories.first['id'];
  }

  void _handleSubmit() async {
    final amount = double.tryParse(_amountController.text.replaceAll(RegExp(r'[^0-9.]'), ''));
    if (amount == null || amount <= 0) return;

    setState(() => _isLoading = true);

    try {
      if (widget.onSave != null) {
        await widget.onSave!({
          'type': _type,
          'amount': amount,
          'wallet_id': _selectedWalletId,
          'category_id': _selectedCategoryId,
          'description': _descriptionController.text.trim(),
          'notes': _notesController.text.trim(),
          'transaction_date': _selectedDate.toIso8601String(),
        });
      }
      if (mounted) Navigator.pop(context);
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Add Transaction')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            SegmentedButton<String>(
              segments: const [
                ButtonSegment(value: 'expense', label: Text('Expense'), icon: Icon(Icons.arrow_upward)),
                ButtonSegment(value: 'income', label: Text('Income'), icon: Icon(Icons.arrow_downward)),
              ],
              selected: {_type},
              onSelectionChanged: (set) => setState(() => _type = set.first),
            ),
            const SizedBox(height: 20),
            TextField(
              controller: _amountController,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(
                labelText: 'Amount',
                prefixText: 'Rp ',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _descriptionController,
              decoration: const InputDecoration(
                labelText: 'Description',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 16),
            if (widget.wallets.isNotEmpty)
              DropdownButtonFormField<String>(
                value: _selectedWalletId,
                decoration: const InputDecoration(labelText: 'Wallet', border: OutlineInputBorder()),
                items: widget.wallets.map((w) => DropdownMenuItem(value: w['id'] as String, child: Text(w['name']))).toList(),
                onChanged: (v) => setState(() => _selectedWalletId = v),
              ),
            const SizedBox(height: 16),
            if (widget.categories.isNotEmpty)
              DropdownButtonFormField<String>(
                value: _selectedCategoryId,
                decoration: const InputDecoration(labelText: 'Category', border: OutlineInputBorder()),
                items: widget.categories.map((c) => DropdownMenuItem(value: c['id'] as String, child: Text(c['name']))).toList(),
                onChanged: (v) => setState(() => _selectedCategoryId = v),
              ),
            const SizedBox(height: 24),
            ElevatedButton(
              onPressed: _isLoading ? null : _handleSubmit,
              style: ElevatedButton.styleFrom(
                padding: const EdgeInsets.symmetric(vertical: 16),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
              child: _isLoading
                  ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Text('Save Transaction', style: TextStyle(fontSize: 16)),
            ),
          ],
        ),
      ),
    );
  }
}
