<?php
/**
 * Class to cache DB and web lookup results.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018 Lee Garner <lee@leegarner.com>
 * @package     quizzer
 * @version     v0.0.1
 * @since       v0.0.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Quizzer;

/**
 * Class for caching quizzes, questions and results.
 * @package quizzer
 */
class Cache
{
    /** Base tag, using plugin name.
     * @const string */
    const TAG = 'quizzer';

    /** Minimum glFusion version supported.
     * @const string */
    const MIN_GVERSION = '2.0.0';

    /**
     * Update the cache.
     *
     * @param   string  $key    Item key
     * @param   mixed   $data   Data, typically an array
     * @param   mixed   $tag    Single tag, or an array
     */
    public static function set($key, $data, $tag='')
    {
        if (version_compare(GVERSION, self::MIN_GVERSION, '<')) return NULL;

        if ($tag == '')
            $tag = array(self::TAG);
        elseif (is_array($tag))
            $tag[] = self::TAG;
        else
            $tag = array($tag, self::TAG);
        $key = self::_makeKey($key);
        \glFusion\Cache::getInstance()->set($key, $data, $tag, 86400);
    }


    /**
     * Delete a single item from the cache by key.
     *
     * @param   string  $key    Base key, e.g. item ID
     */
    public static function delete($key)
    {
        if (version_compare(GVERSION, self::MIN_GVERSION, '<')) {
            return;     // glFusion version doesn't support caching
        }
        $key = self::_makeKey($key);
        \glFusion\Cache::getInstance()->delete($key);
    }




    /**
     * Completely clear the cache.
     * Called after upgrade.
     * Entries matching all tags, including default tag, are removed.
     *
     * @param   mixed   $tag    Single or array of tags
     */
    public static function clear($tag = '')
    {
        if (version_compare(GVERSION, self::MIN_GVERSION, '<')) return NULL;

        $tags = array(self::TAG);
        if (!empty($tag)) {
            if (!is_array($tag)) $tag = array($tag);
            $tags = array_merge($tags, $tag);
        }
        \glFusion\Cache::getInstance()->deleteItemsByTagsAll($tags);
    }


    /**
     * Create a unique cache key.
     *
     * @param   string  $key    Base key
     * @return  string      Encoded key string to use as a cache ID
     */
    private static function _makeKey($key)
    {
        return \glFusion\Cache::getInstance()
            ->createKey(self::TAG . '_' . $key);
    }


    /**
     * Get s single item from the cache.
     *
     * @param   string  $key    Cache key
     * @return  mixed       Cache results or NULL if not found
     */
    public static function get($key)
    {
        if (version_compare(GVERSION, self::MIN_GVERSION, '<')) return NULL;

        $key = self::_makeKey($key);
        if (\glFusion\Cache::getInstance()->has($key)) {
            return \glFusion\Cache::getInstance()->get($key);
        } else {
            return NULL;
        }
    }

}   // class Quizzer\Cache

?>
