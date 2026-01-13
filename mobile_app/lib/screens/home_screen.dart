import 'package:flutter/material.dart';
import 'roster_screen.dart';
import 'waivers_screen.dart';
import 'activity_screen.dart';
import 'team_selection_screen.dart';
import 'free_agents_screen.dart';
import 'login_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _selectedIndex = 0;
  String? _selectedLeague;
  String? _selectedTeam;
  bool _isLoggedIn = false;

  void _onItemTapped(int index) {
    if (index == 4) { // Switch Team
       setState(() {
        _selectedLeague = null;
        _selectedTeam = null;
        _selectedIndex = 0;
      });
    } else {
      setState(() {
        _selectedIndex = index;
      });
    }
  }

  void _onTeamSelected(String league, String team) {
    setState(() {
      _selectedLeague = league;
      _selectedTeam = team;
      _selectedIndex = 0; 
    });
  }

  @override
  Widget build(BuildContext context) {
    if (!_isLoggedIn) {
      return LoginScreen(onLoginSuccess: () {
        setState(() {
          _isLoggedIn = true;
        });
      });
    }

    if (_selectedLeague == null || _selectedTeam == null) {
      return TeamSelectionScreen(onTeamSelected: _onTeamSelected);
    }

    final List<Widget> screens = [
      RosterScreen(league: _selectedLeague!, team: _selectedTeam!),
      FreeAgentsScreen(league: _selectedLeague!),
      WaiversScreen(league: _selectedLeague!),
      ActivityScreen(league: _selectedLeague!),
    ];

    return Scaffold(
      body: screens[_selectedIndex],
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _selectedIndex,
        onTap: _onItemTapped,
        type: BottomNavigationBarType.fixed,
        selectedItemColor: const Color(0xFFE87426),
        unselectedItemColor: Colors.grey,
        items: const [
          BottomNavigationBarItem(
            icon: Icon(Icons.people),
            label: 'Roster',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.search),
            label: 'FA',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.gavel),
            label: 'Waivers',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.notifications),
            label: 'Activity',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.swap_horiz),
            label: 'Team',
          ),
        ],
      ),
    );
  }
}
