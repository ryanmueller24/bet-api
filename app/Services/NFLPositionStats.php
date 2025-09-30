<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
class NFLPositionStats
{
    private $generalStatsNameInJson = [ 
        "gamesPlayed",
    ]; 

    private $passingStatsNameInJson = [
        "completionPct",
        "completions",
        "interceptionPct",
        "interceptions",
        "netPassingYardsPerGame",


    ];

    public function getStats($position, $url) {
        $response = Http::get($url);

        if ($response->successful()) {
            $data = $response->json();  

            switch ($position) {
                case 'QB':
                    return $this->getQBStats($data);
                default:
                    return null;
            }
        }
        
        return null;
    }

    private function getQBStats($data) {
        if (!isset($data['splits']) || !isset($data['splits']['categories'])) {
            return null;
        }

        $stats = [];
        
        foreach ($data['splits']['categories'] as $category) {
            $categoryName = $category['name'];
            
            // Only get interceptions from passing category to avoid duplicates
            if ($categoryName === 'passing') {
                foreach ($category['stats'] as $stat) {
                    if (in_array($stat['name'], $this->passingStatsNameInJson)) {
                        $stats[$stat['name']] = $stat['value'];
                    }
                }
            } elseif ($categoryName === 'general') {
                foreach ($category['stats'] as $stat) {
                    if (in_array($stat['name'], $this->generalStatsNameInJson)) {
                        $stats[$stat['name']] = $stat['value'];
                    }
                }
            }
        }
        
        return $stats;
    }
    

}
