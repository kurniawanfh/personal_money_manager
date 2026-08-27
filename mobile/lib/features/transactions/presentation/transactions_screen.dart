import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

class TransactionsScreen extends StatelessWidget {
  final List<Map<String, dynamic>> transactions;
  final VoidCallback? onAddTransaction;
  final Function(Map<String, dynamic> tx)? onSelectTransaction;

  const TransactionsScreen({
    super.key,
    this.transactions = const [
      {
        'id': 'tx-1',
        'type': 'expense',
        'amount': 45000.0,
        'currency': 'IDR',
        'description': 'Lunch at Warteg',
        'category_name': 'Food & Beverage',
        'wallet_name': 'Cash Pocket',
        'date': '2026-08-28',
      },
      {
        'id': 'tx-2',
        'type': 'income',
        'amount': 8500000.0,
        'currency': 'IDR',
        'description': 'Monthly Salary',
        'category_name': 'Salary',
        'wallet_name': 'Main BCA',
        'date': '2026-08-25',
      },
    ],
    this.onAddTransaction,
    this.onSelectTransaction,
  });

  String _formatCurrency(double amount) {
    final formatter = NumberFormat.currency(locale: 'id_ID', symbol: 'Rp ', decimalDigits: 0);
    return formatter.format(amount);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Transaction Ledger'),
        actions: [
          IconButton(
            icon: const Icon(Icons.filter_list),
            onPressed: () {},
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: onAddTransaction,
        child: const Icon(Icons.add),
      ),
      body: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: transactions.length,
        itemBuilder: (context, index) {
          final tx = transactions[index];
          final type = tx['type'] ?? 'expense';
          final isIncome = type == 'income';
          final isTransfer = type == 'transfer';
          final amount = (tx['amount'] as num?)?.toDouble() ?? 0.0;

          Color iconColor = isIncome ? Colors.green : (isTransfer ? Colors.teal : Colors.red);
          IconData iconData = isIncome ? Icons.arrow_downward : (isTransfer ? Icons.swap_horiz : Icons.arrow_upward);

          return Card(
            margin: const EdgeInsets.only(bottom: 10),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            child: ListTile(
              leading: CircleAvatar(
                backgroundColor: iconColor.withOpacity(0.15),
                child: Icon(iconData, color: iconColor),
              ),
              title: Text(tx['description'] ?? 'Transaction', style: const TextStyle(fontWeight: FontWeight.w600)),
              subtitle: Text('${tx['category_name'] ?? 'Uncategorized'} • ${tx['wallet_name'] ?? 'Wallet'}'),
              trailing: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    '${isIncome ? '+' : (isTransfer ? '' : '-')}${_formatCurrency(amount)}',
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 14,
                      color: iconColor,
                    ),
                  ),
                  Text(tx['date'] ?? '', style: TextStyle(fontSize: 11, color: Colors.grey[600])),
                ],
              ),
              onTap: () => onSelectTransaction?.call(tx),
            ),
          );
        },
      ),
    );
  }
}
