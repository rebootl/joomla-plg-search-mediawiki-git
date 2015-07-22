<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Search.Mediawiki
 *
 * @copyright   Copyright 2015 revamp-it
 * @license     GNU/GPL
 */

// based on example from: https://docs.joomla.org/J3.x:Creating_a_search_plugin

// cem
// 2015-07-02: adaptions marked with [1]
// 2015-07-21: adaptions marked with [2]
// 2015-07-22: adaptions marked with [3], started git tracking

//To prevent accessing the document directly, enter this code:
// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

// [1] commenting out for now
// Require the component's router file (Replace 'nameofcomponent' with the [1] (wrap)
// component your providing the search for
//require_once JPATH_SITE .  '/components/nameofcomponent/helpers/route.php';

// [2] adapted class name
/**
 * All functions need to get wrapped in a class
 *
 * The class name should start with 'PlgSearch' followed by the name of the plugin. [1] (wrap)
 * Joomla calls the class based on the name of the plugin, so it is very important [1] (wrap)
 * that they match
 */
class PlgSearchMediawiki extends JPlugin
{

    // [1] in the other plugins this is not used anymore e.g. contacts, categories
    // [1] instead below is used
	/**
	 * Constructor
	 *
	 * @access      protected
	 * @param       object  $subject The object to observe
	 * @param       array   $config  An array that holds the plugin configuration
	 * @since       1.6
	 */
	/*public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);
		$this->loadLanguage();
	}*/
    protected $autoloadLanguage = true;

    // [2] replaced plugin name
	// Define a function to return an array of search areas. Replace 'nameofplugin' [1] (wrap)
    // with the name of your plugin.
	// Note the value of the array key is normally a language string
	function onContentSearchAreas()
	{
		static $areas = array(
			'Mediawiki' => 'PLG_SEARCH_MEDIAWIKI_MEDIAWIKI'
		);
		return $areas;
	}

	// The real function has to be created. The database connection should be made.
	// The function will be closed with an } at the end of the file.
	/**
	 * The sql must return the following fields that are used in a common display
	 * routine: href, title, section, created, text, browsernav
	 *
	 * @param string Target search string
	 * @param string mathcing option, exact|any|all
	 * @param string ordering option, newest|oldest|popular|alpha|category
	 * @param mixed An array if the search it to be restricted to areas, [1] (wrap)
     * null if search all
	 */
	function onContentSearch( $search_str, $mode='', $ordering='', $areas=null )
	{

        // [1] commenting out for now
		//$user	= JFactory::getUser();
		//$groups	= implode(',', $user->getAuthorisedViewLevels());

        // [1] this function is default/?, can probably stay as is
		// If the array is not correct, return it:
		if (is_array( $areas )) {
			if (!array_intersect( $areas, array_keys( $this->onContentSearchAreas() ) )) {
				return array();
			}
		}

        // [1] probably useful, leaving as is
        // Return Array when nothing was filled in.
        if ($search_str == '') {
            return array();
        }

        // [2] do so
		// Now retrieve the plugin parameters like this:
		//$nameofparameter = $this->params->get('nameofparameter', defaultsetting );
        $wiki_title = $this->params->get('wiki_title', 'Wiki');
        $wiki_baseurl = $this->params->get('wiki_baseurl', '');

        $limit = $this->params->get('search_limit', 20);
        // access a different database
        // https://docs.joomla.org/Connecting_to_an_external_database
        $db_settings = array(); //prevent problems

        // Database driver name
        $db_settings['driver']   = 'mysql';

        // Database host name
        $db_settings['host']     = $this->params->get('db_hostname', 'localhost');
        // User for database authentication
        $db_settings['user']     = $this->params->get('db_user', '');
        // Password for database authentication
        $db_settings['password'] = $this->params->get('db_password', '');
        // Database name
        $db_settings['database'] = $this->params->get('db_database', '');
        // Database prefix (may be empty)
        $db_settings['prefix']   = $this->params->get('db_prefix', '');

        // initialize db driver
        $db_wiki = JDatabaseDriver::getInstance( $db_settings );

        // [2] removed example code

        // get a new JDatabaseQuery object
        // https://api.joomla.org/cms-3/classes/JDatabaseQuery.html
        $query = $db_wiki->getQuery(true);

        // construct a search query
        // an typical example query from: https://www.mediawiki.org/wiki/Manual:Searchindex_table
        //
        // SELECT page_id, page_namespace, page_title
        //   FROM `page`,`searchindex`
        //   WHERE page_id=si_page
        //     AND MATCH(si_text) AGAINST('+ltsp' IN BOOLEAN MODE)
        //     AND page_is_redirect=0 AND page_namespace IN (0) LIMIT 20;
        //
        // this returned 10 results when manually entered
        //
        // partial hints from: http://forum.joomla.org/viewtopic.php?f=651&t=623563
        // (topic old mediawiki search plugin for j1.5)

        // trim leading and trailing expression
        $search_str = trim($search_str);

        // search mode
        // possible all, any, exact
        // exact does not work, since the wiki searchindex text is munged!
        //
        // any: 'apple banana'
        // all: '+apple +banana'
        switch ($mode) {
            case 'any':
                // quote and escape
                $search_expr_qesc = $db_wiki->quote($search_str, true);
                break;
            case 'all':
            default:
                // split
                $words = explode( ' ', $search_str );

                foreach ( $words as $word ) {
                    // escape
                    $word_esc = $db_wiki->escape($word);
                    // reassemble
                    $search_expr_esc .= "+".$word_esc." ";
                }
                // quote
                $search_expr_qesc = $db_wiki->quote($search_expr_esc, false);
        }

        $query->select("page_id, page_namespace, page_title, SUBSTRING(text.old_text, 1, 240) as textpart, rev_timestamp");
        // try to get text around search expr, not working yet...
        //$query->select("page_id, page_namespace, page_title, SUBSTRING(text.old_text, LOCATE(".$search_expr_qesc.", text.old_text)-120, LOCATE(".$search_expr_qesc.", text.old_text)+120)as textpart, rev_timestamp");
        $query->from("page,searchindex,text,revision");

        $query->where("page.page_latest=revision.rev_id AND revision.rev_text_id = text.old_id AND page_id=si_page AND MATCH(si_text) AGAINST(".$search_expr_qesc." IN BOOLEAN MODE) AND page_is_redirect=0 AND page_namespace IN (0)");

        // order
        // popular is not implemented here...
        // (--> that means the default is used then)
        switch ($ordering) {
            // newest first
            case 'newest':
                $order = "rev_timestamp DESC";
                break;
            // oldest first
            case 'oldest':
                $order = "rev_timestamp ASC";
                break;
            // alphabetical ascending
            case 'alpha':
                $order = "page_title ASC";
                break;
            // default: alphabetical ascending
            default:
                $order = "page_title ASC";
        }

        $query->order($order);

        // set query (execute?)
        $db_wiki->setQuery( $query, 0, $limit );

        // get results
        $res_obj_list = $db_wiki->loadObjectList();

        // assemble the result
        $res_arr = array();

        // [3] finished coding below
        foreach($res_obj_list as $key => $obj) {
            // (get the date)
            $date_obj = DateTime::createFromFormat('YmdHis', $obj->rev_timestamp);
            $res_arr[$key] = (object) array(
                'href'        => $wiki_baseurl.'index.php?title='.$obj->page_title,
                'title'       => $obj->page_title,
                'section'     => $wiki_title,
                'created'     => date_format($date_obj, 'Y-m-d H:i:s'),
                'text'        => $obj->textpart,
                'browsernav'  => '1'
            );
        }

        return $res_arr;

/*
        // [1] assemble a pseudo result for testing, adapted from tutorial
        // [1] set variables
        $date_now = date("Y-m-d H:i:s");
        // [1] (get plugin name) copied from below
        $section = JText::_( 'Nameofplugin' );

        $rows[] = (object) array(
            'href'        => "index.php?option=com_helloworld",
            'title'       => "Hello Search!",
            'section'     => $section,
            'created'     => $date_now,
            'text'        => "Hello this is the return text... Debug info:
Database host: ".$db_settings['host'].
"User for database authentication: ".$db_settings['user'].
"Database name: ".$db_settings['database'],
            'browsernav'  => '1'
        );
        return $rows;
*/
	}
}
