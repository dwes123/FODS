import 'package:flutter/material.dart';

class PlayerDetailsScreen extends StatelessWidget {
  final Map<String, dynamic> player;

  const PlayerDetailsScreen({super.key, required this.player});

  @override
  Widget build(BuildContext context) {
    final contracts = player['contracts'] as Map<String, dynamic>? ?? {};

    return Scaffold(
      appBar: AppBar(
        title: Text(player['name'] ?? 'Player Details'),
        backgroundColor: const Color(0xFF2E6DA4),
        foregroundColor: Colors.white,
      ),
      body: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: double.infinity,
              color: const Color(0xFF2E6DA4),
              padding: const EdgeInsets.all(24),
              child: Column(
                children: [
                  CircleAvatar(
                    radius: 40,
                    backgroundColor: Colors.white,
                    child: Text(
                      player['position'] ?? '?',
                      style: const TextStyle(fontSize: 32, fontWeight: FontWeight.bold, color: Color(0xFF2E6DA4)),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Text(
                    player['name'] ?? 'Unknown',
                    style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold, color: Colors.white),
                  ),
                  Text(
                    '${player['mlb_team'] ?? 'N/A'}',
                    style: const TextStyle(fontSize: 18, color: Colors.white70),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(16.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _buildSectionTitle('Roster Status'),
                  _buildInfoRow('40-Man Roster', player['status_40'] == true ? 'Yes' : 'No'),
                  _buildInfoRow('26-Man Roster', player['status_26'] == true ? 'Yes' : 'No'),
                  _buildInfoRow('IL Status', player['status_il'] != null && player['status_il'].isNotEmpty ? player['status_il'] : 'Active'),
                  
                  const SizedBox(height: 24),
                  _buildSectionTitle('Contract Details'),
                  if (contracts.isEmpty)
                    const Text('No contract data available.')
                  else
                    ...contracts.entries.map((entry) => _buildInfoRow(entry.key, '\$${entry.value}')).toList(),
                  
                  const SizedBox(height: 32),
                  Row(
                    children: [
                      Expanded(
                        child: ElevatedButton(
                          onPressed: () {},
                          style: ElevatedButton.styleFrom(backgroundColor: Colors.orange),
                          child: const Text('Move to IL'),
                        ),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: ElevatedButton(
                          onPressed: () {},
                          style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
                          child: const Text('DFA Player'),
                        ),
                      ),
                    ],
                  )
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSectionTitle(String title) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12.0),
      child: Text(
        title,
        style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Color(0xFF2E6DA4)),
      ),
    );
  }

  Widget _buildInfoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4.0),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(color: Colors.grey, fontSize: 16)),
          Text(value, style: const TextStyle(fontWeight: FontWeight.w500, fontSize: 16)),
        ],
      ),
    );
  }
}
