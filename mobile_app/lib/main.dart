import 'package:flutter/material.dart';
import 'screens/home_screen.dart';

void main() {
  runApp(const FodApp());
}

class FodApp extends StatelessWidget {
  const FodApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'FOD Sports',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        primarySwatch: Colors.blue,
        useMaterial3: true,
        primaryColor: const Color(0xFF2E6DA4), // Brand Blue
      ),
      home: const HomeScreen(),
    );
  }
}
