import 'package:flutter/material.dart';
import '../services/api_service.dart';

class RosterScreen extends StatefulWidget {
  final String league;
  final String team;

  const RosterScreen({super.key, required this.league, required this.team});

  @override
  State<RosterScreen> createState() => _RosterScreenState();
}

class _RosterScreenState extends State<RosterScreen> {
  late Future<List<dynamic>> _rosterFuture;

  @override
  void initState() {
    super.initState();
    _rosterFuture = ApiService.fetchRoster(widget.league, widget.team);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('${widget.team} Roster (${widget.league})'),
        backgroundColor: const Color(0xFF2E6DA4),
        foregroundColor: Colors.white,
      ),
      body: FutureBuilder<List<dynamic>>(
        future: _rosterFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          } else if (snapshot.hasError) {
            return Center(child: Text('Error: ${snapshot.error}'));
          } else if (!snapshot.hasData || snapshot.data!.isEmpty) {
            return const Center(child: Text('No players found.'));
          }

          final players = snapshot.data!;

          return ListView.builder(
            itemCount: players.length,
            itemBuilder: (context, index) {
              final player = players[index];
              return ListTile(
                leading: CircleAvatar(
                  backgroundColor: const Color(0xFFE87426), // Brand Orange
                  child: Text(player['position'] ?? '?'),
                ),
                title: Text(player['name']),
                subtitle: Text(player['status_40'] ? '40-Man Roster' : 'Minors'),
                trailing: const Icon(Icons.chevron_right),
                onTap: () {
                  // View player details
                },
              );
            },
          );
        },
      ),
    );
  }
}
