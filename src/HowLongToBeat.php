<?php

namespace ivankayzer\HowLongToBeat;

use Goutte\Client;

class HowLongToBeat
{
    protected $client;

    /**
     * HowLongToBeat constructor.
     * @param Client|null $client
     */
    public function __construct(Client $client = null)
    {
        $this->client = $client ?? new Client();
    }

    public function search($query)
    {
        $crawler = $this->client->request('POST', 'https://howlongtobeat.com/search_results?page=1', [
            'queryString' => $query,
            't' => 'games',
            'sorthead' => 'popular',
            'sortd' => 'Normal Order',
        ]);

        return $crawler->filter('.back_darkish')->each(function ($node) {
            $node = new ListNodeCrawler($node);

            return [
                'id' => $node->getId(),
                'image' => $node->getImage(),
                'name' => $node->getName(),
                'main_story' => $node->getMainStoryTime(),
                'main_and_extra' => $node->getMainAndExtraTime(),
                'completionist' => $node->getCompletionistTime(),
            ];
        });
    }

    public function get($id)
    {
        $crawler = $this->client->request('GET', 'https://howlongtobeat.com/game?id=' . $id);

        $labels = [
            'Developers' => 'Developer',
            'Publishers' => 'Publisher',
            'Genres' => 'Genre',
        ];

        $profileInfo = $crawler->filter('.profile_info')->each(function ($node) use ($labels) {
            $key = str_replace(':', '', $node->filter('strong')->text());
            $key = isset($labels[$key]) ? $labels[$key] : $key;
            return [
                $key => explode(': ', $node->text())[1]
            ];
        });

        $profileInfo = Utilities::flattenArray($profileInfo);

        $profileDetails = $crawler->filter('.profile_details ul li')->each(function ($node) {
            $text = explode(' ', $node->text());
            $key = $text[1];
            $value = str_replace(['.', 'K'], ['', '00'], $text[0]);
            return [$key => $value];
        });

        $profileDetails = Utilities::flattenArray($profileDetails);

        $gameTimes = $crawler->filter('.game_times')->each(function ($node) {
            return [
                'main_story' => $node->filter('li:nth-child(1) div')->text(),
                'main_and_extra' => $node->filter('li:nth-child(2) div')->text(),
                'completionist' => $node->filter('li:nth-child(3) div')->text(),
                'all_styles' => $node->filter('li:nth-child(4) div')->text(),
            ];
        });

        $tables = [];

        $crawler->filter('.game_main_table')->each(function ($node) use (&$tables) {
            $key = $node->filter('thead > tr > td:first-child')->text();
            $columns = $node->filter('thead > tr > td:not(:first-child)')->each(function ($n) {
                return $n->text();
            });
            $rows = $node->filter('tbody > tr > td:first-child')->each(function ($n) {
                return $n->text();
            });
            $tables[$key] = [];

            foreach ($rows as $i => $row) {
                $node->filter(".spreadsheet")->each(function ($n) use ($key, &$tables, $columns) {
                    $title = $n->filter('td:first-child')->text();
                    $data = $n->filter('td:not(:first-child)')->each(function ($nn) use ($key, &$tables, $columns) {
                        return $nn->text();
                    });
                    $tables[$key][$title] = array_combine($columns, $data);
                });
            }
        });

        return array_merge([
            'id' => $id,
            'image' => $crawler->filter('.game_image img')->attr('src'),
            'description' => $crawler->filter('.in.back_primary > p')->text(),
            'developer' => $profileInfo['Developer'],
            'publisher' => $profileInfo['Publisher'],
            'last_update' => $profileInfo['Updated'],
            'playable_on' => $profileInfo['Playable On'],
            'genres' => $profileInfo['Genre'],
            'stats' => [
                'playing' => $profileDetails['Playing'],
                'backlogs' => $profileDetails['Backlogs'],
                'replays' => $profileDetails['Replays'],
                'retired' => $profileDetails['Retired'],
                'rating' => $profileDetails['Rating'],
                'beat' => $profileDetails['Beat'],
            ],
            'general' => $gameTimes,
        ], $tables);
    }
}