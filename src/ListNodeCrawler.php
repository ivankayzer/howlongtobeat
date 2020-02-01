<?php

namespace ivankayzer\HowLongToBeat;

use Symfony\Component\DomCrawler\Crawler;

class ListNodeCrawler
{
    /**
     * @var Crawler
     */
    protected $node;

    /**
     * @var Utilities
     */
    protected $utilities;

    /**
     * ListNodeCrawler constructor.
     * @param Crawler $node
     * @param Utilities|null $utilities
     */
    public function __construct(Crawler $node, Utilities $utilities = null)
    {
        $this->node = $node;
        $this->utilities = $utilities ?? new Utilities();
    }

    /**
     * @return Crawler
     */
    public function getNode()
    {
        return $this->node;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return str_replace('game?id=', '', $this->node->filter('.search_list_image a')->attr('href'));
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->node->filter('.search_list_details h3')->text();
    }

    /**
     * @return string|null
     */
    public function getImage()
    {
        return $this->node->filter('.search_list_image img')->attr('src');
    }

    /**
     * @return array
     */
    public function getTime()
    {
        $keys = $this->node->filter('.search_list_details_block [class^=search_list_tidbit]:nth-child(odd)')->each(function (Crawler $node) {
            return $node->text();
        });

        $values = $this->node->filter('.search_list_details_block [class^=search_list_tidbit]:nth-child(even)')->each(function (Crawler $node) {
            return $this->utilities->formatTime($node->text());
        });

        return array_combine($keys, $values);
    }
}
