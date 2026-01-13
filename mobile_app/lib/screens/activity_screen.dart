import 'package:flutter/material.dart';
import '../services/api_service.dart';

class ActivityScreen extends StatefulWidget {
  final String league;

  const ActivityScreen({super.key, required this.league});

  @override
  State<ActivityScreen> createState() => _ActivityScreenState();
}

class _ActivityScreenState extends State<ActivityScreen> {
  late Future<List<dynamic>> _activityFuture;

  @override
  void initState() {
    super.initState();
    _activityFuture = ApiService.fetchActivity(widget.league);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('League Activity (${widget.league})'),
        backgroundColor: const Color(0xFF2E6DA4),
        foregroundColor: Colors.white,
      ),
      body: FutureBuilder<List<dynamic>>(
        future: _activityFuture,
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
            return const Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.info_outline, size: 48, color: Colors.grey),
                  SizedBox(height: 16),
                  Text('No recent activity for this league.'),
                ],
              ),
            );
          }

          final activities = snapshot.data!;

          return ListView.separated(
            itemCount: activities.length,
            separatorBuilder: (context, index) => const Divider(),
            itemBuilder: (context, index) {
              final item = activities[index];
              return ListTile(
                leading: const Icon(Icons.history, color: Color(0xFFE87426)),
                title: Text(
                  item['summary'] ?? 'No summary',
                  style: const TextStyle(fontSize: 14),
                ),
                subtitle: Text(item['date'] ?? ''),
              );
            },
          );
        },
      ),
    );
  }
}
