import 'package:flutter/material.dart';
import '../services/api_service.dart';

class WaiversScreen extends StatefulWidget {
  final String league;

  const WaiversScreen({super.key, required this.league});

  @override
  State<WaiversScreen> createState() => _WaiversScreenState();
}

class _WaiversScreenState extends State<WaiversScreen> {
  late Future<List<dynamic>> _waiversFuture;

  @override
  void initState() {
    super.initState();
    print('Initializing WaiversScreen for league: ${widget.league}');
    _waiversFuture = ApiService.fetchWaivers(widget.league);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Waiver Wire (${widget.league})'),
        backgroundColor: const Color(0xFF2E6DA4),
        foregroundColor: Colors.white,
      ),
      body: FutureBuilder<List<dynamic>>(
        future: _waiversFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          } else if (snapshot.hasError) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(16.0),
                child: Text('Error: ${snapshot.error}', textAlign: TextAlign.center),
              ),
            );
          } else if (!snapshot.hasData || (snapshot.data as List).isEmpty) {
            return const Center(child: Text('No players on waivers.'));
          }

          final players = snapshot.data!;

          return ListView.builder(
            itemCount: players.length,
            itemBuilder: (context, index) {
              final player = players[index];
              return ListTile(
                leading: const CircleAvatar(
                  backgroundColor: Colors.redAccent,
                  child: Icon(Icons.gavel, color: Colors.white, size: 20),
                ),
                title: Text(player['name']),
                subtitle: Text('Waived by ${player['waiving_team']}'),
                trailing: ElevatedButton(
                  onPressed: () {
                    // Claim logic
                  },
                  child: const Text('Claim'),
                ),
              );
            },
          );
        },
      ),
    );
  }
}
