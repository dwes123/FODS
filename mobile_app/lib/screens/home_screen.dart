import 'package:flutter/material.dart';
import 'roster_screen.dart';
import 'waivers_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _selectedIndex = 0;
  
  static const List<Widget> _screens = [
    RosterScreen(league: 'MLB', team: 'LAD'),
    WaiversScreen(league: 'MLB'),
  ];

  void _onItemTapped(int index) {
    setState(() {
      _selectedIndex = index;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: _screens[_selectedIndex],
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _selectedIndex,
        onTap: _onItemTapped,
        selectedItemColor: const Color(0xFFE87426),
        unselectedItemColor: Colors.grey,
        items: const [
          BottomNavigationBarItem(
            icon: Icon(Icons.people),
            label: 'My Roster',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.gavel),
            label: 'Waivers',
          ),
        ],
      ),
    );
  }
}
