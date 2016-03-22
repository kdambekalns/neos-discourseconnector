<?php
namespace KDambekalns\Neos\DiscourseConnector\TypoScript;

/*
 * This file is part of the Dambekalns.Neos.DiscourseConnector package.
 *
 * (c) Karsten Dambekalns
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Http\Client\CurlEngine;
use TYPO3\TypoScript\TypoScriptObjects\Helpers\FluidView;
use TYPO3\TypoScript\TypoScriptObjects\TemplateImplementation;

/**
 * TopicsListImplementation
 */
class TopicsListImplementation extends TemplateImplementation
{

    /**
     * @Flow\Inject
     * @var \TYPO3\Flow\Http\Client\Browser
     */
    protected $browser;

    /**
     * @return void
     */
    protected function initializeObject()
    {
        $this->browser->setRequestEngine(new CurlEngine());
    }

    /**
     * @param FluidView $fluidView
     * @return void
     */
    protected function initializeView(FluidView $fluidView)
    {
        if ($this['categoryId'] && $this['categoryId']) {
            $categoryId = (int)$this['categoryId'];
            $category = $this->getCategoryData($categoryId);
            $topics = $this->getTopics($category);

            $fluidView->assign('category', $category);
            $fluidView->assign('topics', $topics);
        }
    }

    /**
     * @param array $category
     * @return array
     */
    protected function getTopics(array $category)
    {
        $response = $this->browser->request(sprintf('%s/c/%s.json', $this['baseUri'], $category['idPath']));
        $topics = json_decode($response, true)['topic_list']['topics'];

        $topics = array_filter($topics, function ($topic) {
            if ($this['hidePinnedTopics'] && $topic['pinned']) {
                return false;
            } else {
                switch ($this['displayOpenClosed']) {
                    case 'open':
                        return $topic['closed'] === false;
                    case 'closed':
                        return $topic['closed'] === true;
                    case 'all':
                    default:
                        return true;
                }
            }
        });

        uasort($topics, function ($a, $b) {
            return strcasecmp($a['title'], $b['title']);
        });

        return $topics;
    }

    /**
     * @param integer $categoryId
     * @return array
     */
    protected function getCategoryData($categoryId)
    {
        $response = $this->browser->request(sprintf('%s/c/%u/show.json', $this['baseUri'], $categoryId));
        $category = json_decode($response, true)['category'];

        $category['parentCategories'] = [];
        $category['idPath'] = $category['id'];
        $category['slugPath'] = $category['slug'];

        $parentCategoryId = array_key_exists('parent_category_id', $category) ? $category['parent_category_id'] : null;
        while ($parentCategoryId !== null) {
            $response = $this->browser->request(sprintf('%s/c/%u/show.json', $this['baseUri'], $parentCategoryId));
            $parentCategory = json_decode($response, true)['category'];

            array_unshift($category['parentCategories'], $parentCategory);
            $category['idPath'] = $parentCategory['id'] . '/' . $category['idPath'];
            $category['slugPath'] = $parentCategory['slug'] . '/' . $category['slugPath'];

            $parentCategoryId = array_key_exists('parent_category_id', $parentCategory) ? $parentCategory['parent_category_id'] : null;
        }

        return $category;
    }
}