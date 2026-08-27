import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'transfer_modal.dart';

class WalletsScreen extends StatelessWidget {
  final List<Map<String, dynamic>> wallets;
  final VoidCallback? onAddWallet;
  final Function(Map<String, dynamic> wallet)? onSelectWallet;

  const WalletsScreen({
    super.key,
    this.wallets = const [
      {'id': 'w-1', 'name': 'Main BCA', 'type': 'bank', 'balance': 3500000.0, 'currency': 'IDR'},
      {'id': 'w-2', 'name': 'GoPay E-Wallet', 'type': 'ewallet', 'balance': 420000.0, 'currency': 'IDR'},
      {'id': 'w-3', 'name': 'Cash Pocket', 'type': 'cash', 'balance': 1500000.0, 'currency': 'IDR'},
    ],
    this.onAddWallet,
    this.onSelectWallet,
  });

  String _formatCurrency(double amount) {
    final formatter = NumberFormat.currency(locale: 'id_ID', symbol: 'Rp ', decimalDigits: 0);
    return formatter.format(amount);
  }

  IconData _getWalletIcon(String type) {
    switch (type.toLowerCase()) {
      case 'bank':
        return Icons.account_balance;
      case 'ewallet':
        return Icons.phone_android;
      case 'cash':
        return Icons.money;
      default:
        return Icons.account_balance_wallet;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('My Wallets & Accounts'),
        actions: [
          IconButton(
            icon: const Icon(Icons.swap_horiz_rounded),
            tooltip: 'Transfer Balance',
            onPressed: () {
              showModalBottomSheet(
                context: context,
                isScrollControlled: true,
                shape: const RoundedRectangleBorder(
                  borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
                ),
                builder: (_) => TransferModal(wallets: wallets),
              );
            },
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: onAddWallet,
        child: const Icon(Icons.add),
      ),
      body: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: wallets.length,
        itemBuilder: (context, index) {
          final wallet = wallets[index];
          final balance = (wallet['balance'] as num?)?.toDouble() ?? 0.0;

          return Card(
            margin: const EdgeInsets.only(bottom: 12),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            child: ListTile(
              contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              leading: CircleAvatar(
                backgroundColor: Theme.of(context).primaryColor.withOpacity(0.1),
                child: Icon(_getWalletIcon(wallet['type'] ?? 'bank')),
              ),
              title: Text(wallet['name'] ?? 'Wallet', style: const TextStyle(fontWeight: FontWeight.bold)),
              subtitle: Text((wallet['type'] ?? 'Bank').toString().toUpperCase()),
              trailing: Text(
                _formatCurrency(balance),
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
              ),
              onTap: () => onSelectWallet?.call(wallet),
            ),
          );
        },
      ),
    );
  }
}
