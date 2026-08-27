import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

class PlannedExpenseCard extends StatefulWidget {
  final Map<String, dynamic> plannedExpense;
  final Future<void> Function(String id, double? customActualAmount)? onConfirm;
  final Future<void> Function(String id)? onSkip;

  const PlannedExpenseCard({
    super.key,
    required this.plannedExpense,
    this.onConfirm,
    this.onSkip,
  });

  @override
  State<PlannedExpenseCard> createState() => _PlannedExpenseCardState();
}

class _PlannedExpenseCardState extends State<PlannedExpenseCard> {
  bool _isEditing = false;
  late final TextEditingController _actualAmountController;
  bool _isLoading = false;

  @override
  void initState() {
    super.initState();
    final estimated = (widget.plannedExpense['estimated_idr_amount'] as num?)?.toDouble() ?? 0.0;
    _actualAmountController = TextEditingController(text: estimated.toStringAsFixed(0));
  }

  String _formatCurrency(double amount) {
    final formatter = NumberFormat.currency(locale: 'id_ID', symbol: 'Rp ', decimalDigits: 0);
    return formatter.format(amount);
  }

  void _handleConfirm() async {
    setState(() => _isLoading = true);
    final customAmount = double.tryParse(_actualAmountController.text);
    try {
      if (widget.onConfirm != null) {
        await widget.onConfirm!(widget.plannedExpense['id'], customAmount);
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  void _handleSkip() async {
    setState(() => _isLoading = true);
    try {
      if (widget.onSkip != null) {
        await widget.onSkip!(widget.plannedExpense['id']);
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final subName = widget.plannedExpense['subscription']?['name'] ?? widget.plannedExpense['name'] ?? 'Subscription';
    final estimated = (widget.plannedExpense['estimated_idr_amount'] as num?)?.toDouble() ?? 0.0;
    final dueDate = widget.plannedExpense['due_date'] ?? '';
    final status = widget.plannedExpense['status'] ?? 'pending';
    final isPending = status == 'pending';

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(subName, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                Text(
                  _formatCurrency(estimated),
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: Colors.blueAccent),
                ),
              ],
            ),
            const SizedBox(height: 4),
            Text('Due Date: $dueDate', style: TextStyle(fontSize: 12, color: Colors.grey[600])),
            if (_isEditing && isPending) ...[
              const SizedBox(height: 12),
              TextField(
                controller: _actualAmountController,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(
                  labelText: 'Actual Charged Amount (IDR)',
                  prefixText: 'Rp ',
                  border: OutlineInputBorder(),
                  isDense: true,
                ),
              ),
            ],
            const SizedBox(height: 12),
            if (isPending)
              Row(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  TextButton(
                    onPressed: _isLoading ? null : () => setState(() => _isEditing = !_isEditing),
                    child: Text(_isEditing ? 'Cancel Edit' : 'Edit Amount'),
                  ),
                  const SizedBox(width: 8),
                  OutlinedButton(
                    onPressed: _isLoading ? null : _handleSkip,
                    style: OutlinedButton.styleFrom(foregroundColor: Colors.grey[700]),
                    child: const Text('Skip'),
                  ),
                  const SizedBox(width: 8),
                  ElevatedButton(
                    onPressed: _isLoading ? null : _handleConfirm,
                    child: _isLoading
                        ? const SizedBox(height: 14, width: 14, child: CircularProgressIndicator(strokeWidth: 2))
                        : const Text('Confirm & Post'),
                  ),
                ],
              )
            else
              Text(
                'Status: ${status.toString().toUpperCase()}',
                style: TextStyle(fontWeight: FontWeight.bold, color: status == 'confirmed' ? Colors.green : Colors.grey),
              ),
          ],
        ),
      ),
    );
  }
}
