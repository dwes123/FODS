import 'package:flutter/material.dart';
import '../services/api_service.dart';

class FreeAgentsScreen extends StatefulWidget {
  final String league;

  const FreeAgentsScreen({super.key, required this.league});

  @override
  State<FreeAgentsScreen> createState() => _FreeAgentsScreenState();
}

class _FreeAgentsScreenState extends State<FreeAgentsScreen> {
  final _searchController = TextEditingController();
  Future<List<dynamic>>? _faFuture;

  @override
  void initState() {
    super.initState();
    _fetchFA();
  }

  void _fetchFA() {
    setState(() {
      _faFuture = ApiService.fetchFreeAgents(widget.league, search: _searchController.text);
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Free Agents (${widget.league})'),
        backgroundColor: const Color(0xFF2E6DA4),
        foregroundColor: Colors.white,
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(8.0),
            child: TextField(
              controller: _searchController,
              decoration: InputDecoration(
                hintText: 'Search players...',
                suffixIcon: IconButton(
                  icon: const Icon(Icons.search),
                  onPressed: _fetchFA,
                ),
                border: const OutlineInputBorder(),
              ),
              onSubmitted: (_) => _fetchFA(),
            ),
          ),
          Expanded(
            child: FutureBuilder<List<dynamic>>(
              future: _faFuture,
              builder: (context, snapshot) {
                if (snapshot.connectionState == ConnectionState.waiting) {
                  return const Center(child: CircularProgressIndicator());
                } else if (snapshot.hasError) {
                  return Center(child: Text('Error: ${snapshot.error}'));
                } else if (!snapshot.hasData || snapshot.data!.isEmpty) {
                  return const Center(child: Text('No free agents found.'));
                }

                final players = snapshot.data!;
                return ListView.builder(
                  itemCount: players.length,
                  itemBuilder: (context, index) {
                    final player = players[index];
                    return ListTile(
                      leading: CircleAvatar(
                        child: Text(player['position'] ?? '?'),
                      ),
                      title: Text(player['name'] ?? 'Unknown'),
                      subtitle: Text(player['current_bid'] != null ? 'Current Bid: \$${player['current_bid']}' : 'Available'),
                      trailing: ElevatedButton(
                        onPressed: () {},
                        child: const Text('Bid'),
                      ),
                    );
                  },
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
