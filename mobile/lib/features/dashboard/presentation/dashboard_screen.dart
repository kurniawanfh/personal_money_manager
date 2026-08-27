import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

class DashboardScreen extends StatelessWidget {
  final double totalBalance;
  final double monthlyExpense;
  final double monthlyIncome;
  final int pendingPlannedExpensesCount;
  final int leakAlertsCount;
  final VoidCallback? onVoicePressed;
  final VoidCallback? onAddTransaction;
  final VoidCallback? onTransferPressed;
  final VoidCallback? onPlannedExpensesPressed;
  final VoidCallback? onLeaksPressed;

  const DashboardScreen({
    super.key,
    this.totalBalance = 5420000,
    this.monthlyExpense = 1850000,
    this.monthlyIncome = 8500000,
    this.pendingPlannedExpensesCount = 2,
    this.leakAlertsCount = 1,
    this.onVoicePressed,
    this.onAddTransaction,
    this.onTransferPressed,
    this.onPlannedExpensesPressed,
    this.onLeaksPressed,
  });

  String _formatCurrency(double amount) {
    final formatter = NumberFormat.currency(locale: 'id_ID', symbol: 'Rp ', decimalDigits: 0);
    return formatter.format(amount);
  }

  @override
  Widget build(BuildContext context) {
    final netCashflow = monthlyIncome - monthlyExpense;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Financial Dashboard'),
        actions: [
          IconButton(
            icon: const Icon(Icons.notifications_outlined),
            onPressed: () {},
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Net Worth Card
            Card(
              elevation: 3,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              color: Theme.of(context).colorScheme.primaryContainer,
              child: Padding(
                padding: const EdgeInsets.all(20.0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Total Available Balance', style: Theme.of(context).textTheme.titleSmall),
                    const SizedBox(height: 8),
                    Text(
                      _formatCurrency(totalBalance),
                      key: const Key('total_balance_text'),
                      style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                            fontWeight: FontWeight.bold,
                            color: Theme.of(context).colorScheme.onPrimaryContainer,
                          ),
                    ),
                    const Divider(height: 24),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text('Monthly Expense (Burn)', style: TextStyle(fontSize: 12)),
                            const SizedBox(height: 4),
                            Text(
                              _formatCurrency(monthlyExpense),
                              style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.red),
                            ),
                          ],
                        ),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            const Text('Net Cashflow', style: TextStyle(fontSize: 12)),
                            const SizedBox(height: 4),
                            Text(
                              _formatCurrency(netCashflow),
                              style: TextStyle(
                                fontWeight: FontWeight.bold,
                                color: netCashflow >= 0 ? Colors.green : Colors.red,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),

            // Pending Planned Expenses Alert Banner
            if (pendingPlannedExpensesCount > 0)
              InkWell(
                onTap: onPlannedExpensesPressed,
                child: Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.amber.shade50,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: Colors.amber.shade300),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.schedule, color: Colors.amber),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          '$pendingPlannedExpensesCount pending subscription expenses need confirmation',
                          style: TextStyle(fontWeight: FontWeight.w600, color: Colors.amber.shade900),
                        ),
                      ),
                      const Icon(Icons.arrow_forward_ios, size: 14, color: Colors.amber),
                    ],
                  ),
                ),
              ),
            const SizedBox(height: 12),

            // Potential Money Leak Alert Banner
            if (leakAlertsCount > 0)
              InkWell(
                onTap: onLeaksPressed,
                child: Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.orange.shade50,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: Colors.orange.shade300),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.warning_amber_rounded, color: Colors.orange),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          'Potential Money Leak detected: $leakAlertsCount advisory alerts',
                          style: TextStyle(fontWeight: FontWeight.w600, color: Colors.orange.shade900),
                        ),
                      ),
                      const Icon(Icons.arrow_forward_ios, size: 14, color: Colors.orange),
                    ],
                  ),
                ),
              ),
            const SizedBox(height: 20),

            // Quick Actions Grid
            Text('Quick Actions', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: _QuickActionCard(
                    icon: Icons.mic_rounded,
                    label: 'Voice Log',
                    color: Colors.purple,
                    onTap: onVoicePressed,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _QuickActionCard(
                    icon: Icons.add_circle_outline,
                    label: 'Add Expense',
                    color: Colors.blue,
                    onTap: onAddTransaction,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _QuickActionCard(
                    icon: Icons.swap_horiz,
                    label: 'Transfer',
                    color: Colors.teal,
                    onTap: onTransferPressed,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _QuickActionCard extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback? onTap;

  const _QuickActionCard({required this.icon, required this.label, required this.color, this.onTap});

  @override
  Widget build(BuildContext context) {
    return Card(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 16.0),
          child: Column(
            children: [
              CircleAvatar(backgroundColor: color.withOpacity(0.15), child: Icon(icon, color: color)),
              const SizedBox(height: 8),
              Text(label, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 12)),
            ],
          ),
        ),
      ),
    );
  }
}
