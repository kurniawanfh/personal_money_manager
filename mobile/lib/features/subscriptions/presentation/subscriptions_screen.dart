import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

class SubscriptionsScreen extends StatelessWidget {
  final List<Map<String, dynamic>> subscriptions;
  final VoidCallback? onAddSubscription;
  final Function(Map<String, dynamic> sub)? onTogglePause;

  const SubscriptionsScreen({
    super.key,
    this.subscriptions = const [
      {
        'id': 's-1',
        'name': 'Netflix Premium',
        'original_currency': 'IDR',
        'original_amount': 186000.0,
        'estimated_idr_amount': 186000.0,
        'billing_cycle': 'monthly',
        'billing_day': 15,
        'next_billing_date': '2026-09-15',
        'status': 'active',
      },
      {
        'id': 's-2',
        'name': 'ChatGPT Plus',
        'original_currency': 'USD',
        'original_amount': 20.0,
        'estimated_idr_amount': 325000.0,
        'billing_cycle': 'monthly',
        'billing_day': 22,
        'next_billing_date': '2026-09-22',
        'status': 'active',
      },
    ],
    this.onAddSubscription,
    this.onTogglePause,
  });

  String _formatCurrency(double amount) {
    final formatter = NumberFormat.currency(locale: 'id_ID', symbol: 'Rp ', decimalDigits: 0);
    return formatter.format(amount);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Subscriptions Tracker'),
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: onAddSubscription,
        child: const Icon(Icons.add),
      ),
      body: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: subscriptions.length,
        itemBuilder: (context, index) {
          final sub = subscriptions[index];
          final isValas = (sub['original_currency'] ?? 'IDR') != 'IDR';
          final isActive = (sub['status'] ?? 'active') == 'active';

          return Card(
            margin: const EdgeInsets.only(bottom: 12),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            child: Padding(
              padding: const EdgeInsets.all(16.0),
              child: Row(
                children: [
                  CircleAvatar(
                    backgroundColor: isActive ? Colors.indigo.shade50 : Colors.grey.shade200,
                    child: Icon(
                      Icons.repeat_rounded,
                      color: isActive ? Colors.indigo : Colors.grey,
                    ),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          sub['name'] ?? 'Subscription',
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          'Renews on ${sub['next_billing_date']} • ${sub['billing_cycle']}',
                          style: TextStyle(fontSize: 12, color: Colors.grey[600]),
                        ),
                        if (isValas)
                          Text(
                            'Original: ${sub['original_currency']} ${sub['original_amount']}',
                            style: const TextStyle(fontSize: 11, color: Colors.blueGrey),
                          ),
                      ],
                    ),
                  ),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(
                        _formatCurrency((sub['estimated_idr_amount'] as num?)?.toDouble() ?? 0.0),
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                      ),
                      const SizedBox(height: 6),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                        decoration: BoxDecoration(
                          color: isActive ? Colors.green.shade50 : Colors.grey.shade100,
                          borderRadius: BorderRadius.circular(6),
                          border: Border.all(color: isActive ? Colors.green.shade300 : Colors.grey),
                        ),
                        child: Text(
                          sub['status']?.toString().toUpperCase() ?? 'ACTIVE',
                          style: TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.bold,
                            color: isActive ? Colors.green.shade800 : Colors.grey.shade700,
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
