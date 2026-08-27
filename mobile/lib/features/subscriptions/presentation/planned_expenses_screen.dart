import 'package:flutter/material.dart';
import 'planned_expense_card.dart';

class PlannedExpensesScreen extends StatelessWidget {
  final List<Map<String, dynamic>> pendingPlannedExpenses;
  final Future<void> Function(String id, double? customAmount)? onConfirm;
  final Future<void> Function(String id)? onSkip;

  const PlannedExpensesScreen({
    super.key,
    this.pendingPlannedExpenses = const [
      {
        'id': 'pe-1',
        'subscription': {'name': 'Spotify Family'},
        'estimated_idr_amount': 86900.0,
        'due_date': '2026-08-28',
        'status': 'pending',
      },
      {
        'id': 'pe-2',
        'subscription': {'name': 'ChatGPT Plus (USD)'},
        'estimated_idr_amount': 320000.0,
        'due_date': '2026-08-29',
        'status': 'pending',
      },
    ],
    this.onConfirm,
    this.onSkip,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Pending Planned Approvals')),
      body: pendingPlannedExpenses.isEmpty
          ? const Center(child: Text('No pending planned expenses.'))
          : ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: pendingPlannedExpenses.length,
              itemBuilder: (context, index) {
                return PlannedExpenseCard(
                  plannedExpense: pendingPlannedExpenses[index],
                  onConfirm: onConfirm,
                  onSkip: onSkip,
                );
              },
            ),
    );
  }
}
