import 'package:flutter/material.dart';
import '../services/api_service.dart';

class TeamSelectionScreen extends StatefulWidget {
  final Function(String league, String team) onTeamSelected;

  const TeamSelectionScreen({super.key, required this.onTeamSelected});

  @override
  State<TeamSelectionScreen> createState() => _TeamSelectionScreenState();
}

class _TeamSelectionScreenState extends State<TeamSelectionScreen> {
  late Future<Map<String, dynamic>> _teamsFuture;

  @override
  void initState() {
    super.initState();
    _teamsFuture = ApiService.fetchUserTeams();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Select Your Team'),
        backgroundColor: const Color(0xFF2E6DA4),
        foregroundColor: Colors.white,
      ),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _teamsFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          } else if (snapshot.hasError) {
            // If error, show a button to manually enter for now (or handle login)
            return Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Icon(Icons.lock_outline, size: 64, color: Colors.grey),
                  const SizedBox(height: 16),
                  Text('Unable to fetch teams: ${snapshot.error}'),
                  const SizedBox(height: 8),
                  const Text('Note: This usually means you need to be logged in.'),
                  const SizedBox(height: 20),
                  ElevatedButton(
                    onPressed: () => widget.onTeamSelected('MLB', 'LAD'),
                    child: const Text('Continue as Guest (LAD)'),
                  )
                ],
              ),
            );
          }

          final mlbTeams = snapshot.data?['mlb_leagues'] as List<dynamic>? ?? [];
          final nbaTeams = snapshot.data?['nba_leagues'] as List<dynamic>? ?? [];

          if (mlbTeams.isEmpty && nbaTeams.isEmpty) {
            return const Center(child: Text('No managed teams found for your account.'));
          }

          return ListView(
            children: [
              if (mlbTeams.isNotEmpty) ...[
                const ListTile(
                  title: Text('MLB Leagues', style: TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF2E6DA4))),
                  tileColor: Color(0xFFF0F0F0),
                ),
                ...mlbTeams.map((team) => ListTile(
                  leading: const Icon(Icons.sports_baseball, color: Color(0xFFE87426)),
                  title: Text(team['fantasy_team_id'] ?? 'Unknown Team'),
                  subtitle: Text('League: ${team['league_id']}'),
                  onTap: () => widget.onTeamSelected(team['league_id'], team['fantasy_team_id']),
                )),
              ],
              if (nbaTeams.isNotEmpty) ...[
                const ListTile(
                  title: Text('NBA Leagues', style: TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF2E6DA4))),
                  tileColor: Color(0xFFF0F0F0),
                ),
                ...nbaTeams.map((team) => ListTile(
                  leading: const Icon(Icons.sports_basketball, color: Colors.orange),
                  title: Text(team['fantasy_team_id'] ?? 'Unknown Team'),
                  subtitle: Text('League: ${team['league_id']}'),
                  onTap: () => widget.onTeamSelected(team['league_id'], team['fantasy_team_id']),
                )),
              ],
            ],
          );
        },
      ),
    );
  }
}
