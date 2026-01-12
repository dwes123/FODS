import 'dart:convert';
import 'package:http/http.dart' as http;

class ApiService {
  static const String baseUrl = 'https://staging-9cb1-frontofficedynastysports.wpcomstaging.com/wp-json/fod/v1';

  static Future<List<dynamic>> fetchRoster(String league, String team) async {
    final response = await http.get(Uri.parse('$baseUrl/roster/$league/$team'));

    if (response.statusCode == 200) {
      return json.decode(response.body);
    } else {
      throw Exception('Failed to load roster');
    }
  }

  static Future<List<dynamic>> fetchWaivers(String league) async {
    final response = await http.get(Uri.parse('$baseUrl/waivers/$league'));

    if (response.statusCode == 200) {
      return json.decode(response.body);
    } else {
      throw Exception('Failed to load waivers');
    }
  }
}
