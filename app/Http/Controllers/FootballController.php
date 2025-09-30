<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\NFLPositionStats;

class FootballController extends Controller
{
    private $nflPositions = [
        'QB' => 'Quarterback',
        'RB' => 'Running Back',
        'WR' => 'Wide Receiver',
        'TE' => 'Tight End',
        'C' => 'Center',
        'G' => 'Guard',
        'T' => 'Tackle',
        'S' => 'Safety',
        'CB' => 'Cornerback',
        'LB' => 'Linebacker',
        'DE' => 'Defensive End',
        'DT' => 'Defensive Tackle',
        'SS' => 'Strong Safety',
        'FS' => 'Free Safety',
    ];
    public function getAllTeams()
    {
        $url = "https://site.api.espn.com/apis/site/v2/sports/football/nfl/teams";
        
        try {
            $response = Http::get($url);

            if ($response->successful()) {
                $data = $response->json();
                
                // Extract teams from the nested structure
                $teams = [];
                if (isset($data['sports'][0]['leagues'][0]['teams'])) {
                    foreach ($data['sports'][0]['leagues'][0]['teams'] as $teamData) {
                        $team = $teamData['team'];
                        $teams[] = [
                            'id' => $team['id'],
                            'displayName' => $team['displayName'],
                            'logo' => $team['logos'][0]['href'] ?? null, // Get the first logo URL
                            'abbreviation' => $team['abbreviation'],
                            'location' => $team['location']
                        ];
                    }
                }
                
                return response()->json([
                    'teams' => $teams,
                    'total' => count($teams)
                ]);
            } else {
                return response()->json([
                    'error' => 'Failed to fetch data',
                    'status' => $response->status()
                ], $response->status());
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Exception occurred',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getTeamById($id)
    {
        $url = "https://site.api.espn.com/apis/site/v2/sports/football/nfl/teams/$id";
        
        try {
            $response = Http::get($url);

            if ($response->successful()) {
                $data = $response->json();

                // Extract team and record data
                $team = $data['team'] ?? null;
                $record = $team['record'] ?? null;
                
                if ($team && $record) {
                    $recordData = [];
                    
                    // Extract record items (total, home, away records)
                    if (isset($record['items'])) {
                        foreach ($record['items'] as $item) {
                            $recordData[] = [
                                'type' => $item['type'] ?? null,
                                'description' => $item['description'] ?? null,
                                'summary' => $item['summary'] ?? null,
                                'stats' => $item['stats'] ?? []
                            ];
                        }
                    }
                    
                    return response()->json([
                        'team' => [
                            'id' => $team['id'],
                            'displayName' => $team['displayName'],
                            'abbreviation' => $team['abbreviation'],
                            'location' => $team['location'],
                            'logo' => $team['logos'][0]['href'] ?? null
                        ],
                        'record' => $recordData
                    ]);
                } else {
                    return response()->json([
                        'error' => 'Team or record data not found'
                    ], 404);
                }
            } else {
                return response()->json([
                    'error' => 'Failed to fetch data',
                    'status' => $response->status()
                ], $response->status());
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Exception occurred',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getTeamRoster($id) 
    {
        $players = [];
        $page = 1;
        $listOfPositions = [];
    
        do {
            $url = "http://sports.core.api.espn.com/v2/sports/football/leagues/nfl/seasons/2025/teams/{$id}/athletes?lang=en&region=us&page={$page}";
            $response = Http::get($url);
            $roster = $response->json();
    
            if (!isset($roster['items'])) {
                break;
            }
    
            foreach ($roster['items'] as $item) {
                $playerUrl = $item['$ref'];
                $playerRes = Http::get($playerUrl)->json();

                if (!in_array($playerRes['position']['displayName'], $listOfPositions)) {
                    $listOfPositions[] = $playerRes['position']['displayName'];
                }

                $players[] = [
                    'id' => $playerRes['id'] ?? null,
                    'name' => $playerRes['displayName'] ?? null,
                    'jersey' => $playerRes['jersey'] ?? null,
                    'position' => $playerRes['position']['displayName'] ?? null,
                    'position_abbreviation' => $playerRes['position']['abbreviation'] ?? null,
                    'headshot' => $playerRes['headshot']['href'] ?? null,
                ];
            }
    
            $page++;
        } while (isset($roster['pageCount']) && $page <= $roster['pageCount']);

        // Define position order
        $positionOrder = [
            // Offense
            'Quarterback' => 1,
            'QB' => 1,
            'Running Back' => 2,
            'RB' => 2,
            'Wide Receiver' => 3,
            'WR' => 3,
            'Tight End' => 4,
            'TE' => 4,
            'Center' => 5,
            'C' => 5,
            'Guard' => 6,
            'G' => 6,
            'Offensive Tackle' => 7,
            'OT' => 7,
            'Long Snapper' => 8,
            'LS' => 8,
            
            // Defense
            'Defensive End' => 9,
            'DE' => 9,
            'Defensive Tackle' => 10,
            'DT' => 10,
            'Linebacker' => 11,
            'LB' => 11,
            'Cornerback' => 12,
            'CB' => 12,
            'Safety' => 13,
            'S' => 13,
            
            // Special Teams
            'Place Kicker' => 14,
            'K' => 14,
            'Punter' => 15,
            'P' => 15,
        ];

        // Sort players by position order
        usort($players, function($a, $b) use ($positionOrder) {
            $posA = $positionOrder[$a['position']] ?? 999; // Unknown positions go to end
            $posB = $positionOrder[$b['position']] ?? 999;
            
            if ($posA == $posB) {
                // If same position, sort by jersey number
                return ($a['jersey'] ?? 999) <=> ($b['jersey'] ?? 999);
            }
            
            return $posA <=> $posB;
        });

        Log::info($listOfPositions);
    
        return response()->json([
            'total' => count($players),
            'players' => $players,
            'positions' => $listOfPositions
        ]);
    }

    public function individualPlayerStats($playerId, String $position)
    {
        $url = "http://sports.core.api.espn.com/v2/sports/football/leagues/nfl/athletes/{$playerId}/statistics/0?lang=en&region=us";
        
        try {
            $nflPositionStats = new NFLPositionStats();
            $stats = $nflPositionStats->getStats($position, $url);

            return response()->json($stats);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Exception occurred',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}