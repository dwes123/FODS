import 'dart:convert';
import 'package:http/http.dart' as http;

class ApiService {
  static const String baseUrl = 'https://staging-9cb1-frontofficedynastysports.wpcomstaging.com/wp-json/fod/v1';
  static String? _authToken; // Simulated or JWT token

  static void setToken(String token) {
    _authToken = token;
  }

  static Map<String, String> _getHeaders() {
    return {
      'Content-Type': 'application/json',
      if (_authToken != null && _authToken != "simulated_token") 
        'Authorization': 'Bearer $_authToken',
    };
  }

  static Future<bool> login(String username, String password) async {
    // For now, we simulate success if not empty. 
    // In production, you'd call /wp-json/jwt-auth/v1/token
    if (username.isNotEmpty && password.isNotEmpty) {
      _authToken = "simulated_token"; // Placeholder
      return true;
    }
    return false;
  }

  static Future<List<dynamic>> fetchRoster(String league, String team) async {
    final response = await http.get(Uri.parse('$baseUrl/roster/$league/$team'), headers: _getHeaders());

    if (response.statusCode == 200) {
      return json.decode(response.body);
    } else {
      throw Exception('Failed to load roster');
    }
  }

  static Future<List<dynamic>> fetchWaivers(String league) async {
    final url = '$baseUrl/waivers/$league';
    print('Fetching Waivers from: $url');
    final response = await http.get(Uri.parse(url), headers: _getHeaders());

    if (response.statusCode == 200) {
      return json.decode(response.body);
    } else {
      throw Exception('Failed to load waivers: ${response.statusCode} - ${response.body} (URL: $url)');
    }
  }

  static Future<Map<String, dynamic>> fetchUserTeams() async {
    final response = await http.get(Uri.parse('$baseUrl/my-teams'), headers: _getHeaders());

    if (response.statusCode == 200) {
      return json.decode(response.body);
    } else {
      throw Exception('Failed to load teams. Make sure you are logged in.');
    }
  }

  static Future<List<dynamic>> fetchActivity(String league) async {
    final response = await http.get(Uri.parse('$baseUrl/activity/$league'), headers: _getHeaders());

    if (response.statusCode == 200) {
      return json.decode(response.body);
    } else {
      throw Exception('Failed to load activity feed');
    }
  }

  static Future<List<dynamic>> fetchFreeAgents(String league, {String search = ''}) async {
    final response = await http.get(
      Uri.parse('$baseUrl/free-agents/$league?s=$search'),
      headers: _getHeaders(),
    );

    if (response.statusCode == 200) {
      return json.decode(response.body);
    } else {
      throw Exception('Failed to load free agents');
    }
  }
}
