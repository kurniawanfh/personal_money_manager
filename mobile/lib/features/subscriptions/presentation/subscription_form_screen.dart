import 'package:flutter/material.dart';

class SubscriptionFormScreen extends StatefulWidget {
  final List<Map<String, dynamic>> wallets;
  final List<Map<String, dynamic>> categories;
  final Future<void> Function(Map<String, dynamic> data)? onSave;

  const SubscriptionFormScreen({
    super.key,
    this.wallets = const [],
    this.categories = const [],
    this.onSave,
  });

  @override
  State<SubscriptionFormScreen> createState() => _SubscriptionFormScreenState();
}

class _SubscriptionFormScreenState extends State<SubscriptionFormScreen> {
  final _nameController = TextEditingController();
  final _amountController = TextEditingController();
  final _estimatedIdrController = TextEditingController();
  String _currency = 'IDR';
  String _billingCycle = 'monthly';
  int _billingDay = 1;
  String? _selectedWalletId;
  String? _selectedCategoryId;
  bool _remindH3 = true;
  bool _remindH1 = true;
  bool _isLoading = false;

  void _handleSave() async {
    final amount = double.tryParse(_amountController.text.replaceAll(RegExp(r'[^0-9.]'), ''));
    if (_nameController.text.trim().isEmpty || amount == null || amount <= 0) return;

    final estimatedIdr = _currency == 'IDR'
        ? amount
        : (double.tryParse(_estimatedIdrController.text.replaceAll(RegExp(r'[^0-9.]'), '')) ?? amount * 16000);

    setState(() => _isLoading = true);

    try {
      if (widget.onSave != null) {
        await widget.onSave!({
          'name': _nameController.text.trim(),
          'original_currency': _currency,
          'original_amount': amount,
          'estimated_idr_amount': estimatedIdr,
          'billing_cycle': _billingCycle,
          'billing_day': _billingDay,
          'wallet_id': _selectedWalletId,
          'category_id': _selectedCategoryId,
          'remind_h3': _remindH3,
          'remind_h1': _remindH1,
        });
      }
      if (mounted) Navigator.pop(context);
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final isValas = _currency != 'IDR';

    return Scaffold(
      appBar: AppBar(title: const Text('Add Subscription')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            TextField(
              controller: _nameController,
              decoration: const InputDecoration(labelText: 'Subscription Name (e.g. Netflix)', border: OutlineInputBorder()),
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                SizedBox(
                  width: 100,
                  child: DropdownButtonFormField<String>(
                    value: _currency,
                    decoration: const InputDecoration(labelText: 'Currency', border: OutlineInputBorder()),
                    items: ['IDR', 'USD', 'EUR', 'SGD']
                        .map((c) => DropdownMenuItem(value: c, child: Text(c)))
                        .toList(),
                    onChanged: (v) => setState(() => _currency = v ?? 'IDR'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: TextField(
                    controller: _amountController,
                    keyboardType: TextInputType.number,
                    decoration: InputDecoration(
                      labelText: 'Original Amount',
                      prefixText: '$_currency ',
                      border: const OutlineInputBorder(),
                    ),
                  ),
                ),
              ],
            ),
            if (isValas) ...[
              const SizedBox(height: 16),
              TextField(
                controller: _estimatedIdrController,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(
                  labelText: 'Estimated IDR Amount',
                  prefixText: 'Rp ',
                  helperText: 'Base currency estimation for budgeting',
                  border: OutlineInputBorder(),
                ),
              ),
            ],
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: DropdownButtonFormField<String>(
                    value: _billingCycle,
                    decoration: const InputDecoration(labelText: 'Cycle', border: OutlineInputBorder()),
                    items: ['monthly', 'yearly', 'weekly']
                        .map((c) => DropdownMenuItem(value: c, child: Text(c.toUpperCase())))
                        .toList(),
                    onChanged: (v) => setState(() => _billingCycle = v ?? 'monthly'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: DropdownButtonFormField<int>(
                    value: _billingDay,
                    decoration: const InputDecoration(labelText: 'Billing Day', border: OutlineInputBorder()),
                    items: List.generate(31, (i) => i + 1)
                        .map((d) => DropdownMenuItem(value: d, child: Text('Day $d')))
                        .toList(),
                    onChanged: (v) => setState(() => _billingDay = v ?? 1),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            SwitchListTile(
              title: const Text('Remind H-3 before renewal'),
              value: _remindH3,
              onChanged: (v) => setState(() => _remindH3 = v),
            ),
            SwitchListTile(
              title: const Text('Remind H-1 before renewal'),
              value: _remindH1,
              onChanged: (v) => setState(() => _remindH1 = v),
            ),
            const SizedBox(height: 24),
            ElevatedButton(
              onPressed: _isLoading ? null : _handleSave,
              style: ElevatedButton.styleFrom(
                padding: const EdgeInsets.symmetric(vertical: 16),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
              child: _isLoading
                  ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Text('Save Subscription', style: TextStyle(fontSize: 16)),
            ),
          ],
        ),
      ),
    );
  }
}
