<?php
/**
 * 事件相关函数.
 */

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

//###############################################################################################################

/**
 * 验证登录.
 *
 * @param bool $throwException
 *
 * @throws Exception
 *
 * @return bool
 */
function VerifyLogin($throwException = true)
{
    global $zbp;
    /* @var Member $m */
    $m = null;
    if ($zbp->Verify_MD5(trim(GetVars('username', 'POST')), trim(GetVars('password', 'POST')), $m)) {
        $zbp->user = $m;
        $sd = (float) GetVars('savedate');
        $sd = ($sd < 1) ? 1 : $sd; // must >= 1 day
        $sdt = (time() + 3600 * 24 * $sd);
        SetLoginCookie($m, (int) $sdt);

        foreach ($GLOBALS['hooks']['Filter_Plugin_VerifyLogin_Succeed'] as $fpname => &$fpsignal) {
            $fpname();
        }

        return true;
    } elseif ($throwException) {
        $zbp->ShowError(8, __FILE__, __LINE__);
    } else {
        return false;
    }
}

/**
 * 设置登录Cookie，直接登录该用户.
 *
 * @param Member $user
 * @param int    $cookieTime
 *
 * @return bool
 */
function SetLoginCookie($user, $cookieTime)
{
    global $zbp;
    $addinfo = array();
    $addinfo['chkadmin'] = (int) $zbp->CheckRights('admin');
    $addinfo['chkarticle'] = (int) $zbp->CheckRights('ArticleEdt');
    $addinfo['levelname'] = $user->LevelName;
    $addinfo['userid'] = $user->ID;
    $addinfo['useralias'] = $user->StaticName;
    $token = $zbp->GenerateUserToken($user, $cookieTime);
    $secure = HTTP_SCHEME == 'https://';
    setcookie('username_' . hash("crc32b", $zbp->guid), $user->Name, $cookieTime, $zbp->cookiespath, '', $secure, false);
    setcookie('token_' . hash("crc32b", $zbp->guid), $token, $cookieTime, $zbp->cookiespath, '', $secure, $zbp->cookie_tooken_httponly);
    setcookie('addinfo' . str_replace('/', '', $zbp->cookiespath), json_encode($addinfo), $cookieTime, $zbp->cookiespath, '', $secure, false);

    return true;
}

/**
 * 注销登录.
 */
function Logout()
{
    global $zbp;

    setcookie('username_' . crc32($zbp->guid), '', (time() - 3600), $zbp->cookiespath);
    setcookie('password', '', (time() - 3600), $zbp->cookiespath);
    setcookie('token_' . crc32($zbp->guid), '', (time() - 3600), $zbp->cookiespath);
    setcookie("addinfo" . str_replace('/', '', $zbp->cookiespath), '', (time() - 3600), $zbp->cookiespath);

    foreach ($GLOBALS['hooks']['Filter_Plugin_Logout_Succeed'] as $fpname => &$fpsignal) {
        $fpname();
    }
}

//###############################################################################################################

/**
 * 获取文章.
 *
 * @param mixed $idorname    文章id 或 名称、别名 (1.7支持复杂的array参数,$count为array时后面的参数可以不设)
 * @param array $option |null
 *
 * @return Post
 */
function GetPost($idorname, $option = null)
{
    //新版本的使用说明请看
    //https://wiki.zblogcn.com/doku.php?id=zblogphp:development:functions:getpost
    global $zbp;
    $post = null;
    $id = null;
    $title = null;
    $alias = null;
    $titleoralias = null;

    if (is_array($idorname)) {
        $args = $idorname;
        if (array_key_exists('idorname', $args)) {
            $idorname = $args['idorname'];
        } else {
            $idorname = null;
        }
        if (array_key_exists('id', $args)) {
            $id = $args['id'];
            unset($args['id']);
        }
        if (array_key_exists('title', $args)) {
            $title = $args['title'];
            unset($args['title']);
        }
        if (array_key_exists('alias', $args)) {
            $alias = $args['alias'];
            unset($args['alias']);
        }
        if (array_key_exists('titleoralias', $args)) {
            $titleoralias = $args['titleoralias'];
            unset($args['titleoralias']);
        }
        if (array_key_exists('option', $args)) {
            $option = $args['option'];
            unset($args['option']);
        }
        if (!is_array($option)) {
            $option = array();
        }
        $option = array_merge($args, $option);
        unset($args);
    }

    if (!is_array($option)) {
        $option = array();
    }
    if (!array_key_exists('post_type', $option)) {
        $option['post_type'] = null;
    }
    if (!array_key_exists('post_status', $option)) {
        $option['post_status'] = 0;
    }
    if (!array_key_exists('only_article', $option)) {
        $option['only_article'] = false;
    }
    if (!array_key_exists('only_page', $option)) {
        $option['only_page'] = false;
    }

    $w = array();
    if ($option['post_type'] !== null) {
        $w[] = array('=', 'log_Type', (int) $option['post_type']);
    } elseif ($option['only_article'] == true) {
        $w[] = array('=', 'log_Type', '0');
    } elseif ($option['only_page'] == true) {
        $w[] = array('=', 'log_Type', '1');
    }

    if ($option['post_status'] !== null) {
        $w[] = array('=', 'log_Status', (int) $option['post_status']);
    }

    $option2 = $option;
    unset($option2['post_type'], $option2['post_status'], $option2['only_article'], $option2['only_page']);

    if (is_null($id) === false) {
        $w[] = array('=', 'log_ID', (int) $id);
    }elseif (is_null($title) === false) {
        $w[] = array('=', 'log_Title', $title);
    }elseif (is_null($alias) === false) {
        $w[] = array('=', 'log_Alias', $alias);
    }elseif (is_null($titleoralias) === false) {
        $w[] = array('array', array(array('log_Alias', $titleoralias), array('log_Title', $titleoralias)));
    }elseif (is_string($idorname)) {
        $w[] = array('array', array(array('log_Alias', $idorname), array('log_Title', $idorname)));
    } elseif (is_int($idorname)) {
        $w[] = array('=', 'log_ID', (int) $idorname);
    } else {
        $w[] = array('=', 'log_ID', '');
    }

    $articles = $zbp->GetPostList('*', $w, null, 1, $option2);
    if (count($articles) == 0) {
        $post = new Post();
    } else {
        $post = $articles[0];
    }

    foreach ($GLOBALS['hooks']['Filter_Plugin_GetPost_Result'] as $fpname => &$fpsignal) {
        $fpreturn = $fpname($post);
    }

    return $post;
}

/**
 * 获取文章列表. 
 *
 * @param int  $count  数量 (1.7支持复杂的array参数,$count为array时后面的参数可以不设)
 * @param null $cate   分类ID
 * @param null $auth   用户ID
 * @param null $date   日期
 * @param null $tags   标签
 * @param null $search 搜索关键词
 * @param null $option
 *
 * @return array|mixed
 */
function GetList($count = 10, $cate = null, $auth = null, $date = null, $tags = null, $search = null, $option = null)
{
    //新版本的使用说明请看
    //https://wiki.zblogcn.com/doku.php?id=zblogphp:development:functions:getlist
    global $zbp;
    $args = array();
    if (is_array($count)) {
        $args = $count;
        if (array_key_exists('count', $args)) {
            $count = (int) $args['count'];
            unset($args['count']);
        } else {
            $count = 10;
        }
        if (array_key_exists('category', $args)) {
            $cate = $args['category'];
            unset($args['category']);
        }
        if (array_key_exists('cate', $args)) {
            $cate = $args['cate'];
            unset($args['cate']);
        }
        if (array_key_exists('author', $args)) {
            $auth = $args['author'];
            unset($args['author']);
        }
        if (array_key_exists('auth', $args)) {
            $auth = $args['auth'];
            unset($args['auth']);
        }
        if (array_key_exists('date', $args)) {
            $date = $args['date'];
            unset($args['date']);
        }
        if (array_key_exists('tags', $args)) {
            $tags = $args['tags'];
            unset($args['tags']);
        }
        if (array_key_exists('search', $args)) {
            $search = $args['search'];
            unset($args['search']);
        }
        if (array_key_exists('option', $args)) {
            $option = $args['option'];
            unset($args['option']);
        }
        if (!is_array($option)) {
            $option = array();
        }
        $option = array_merge($args, $option);  
        unset($args); 
    }

    if (!is_array($option)) {
        $option = array();
    }
    if (!array_key_exists('post_type', $option)) {
        $option['post_type'] = null;
    }
    if (!array_key_exists('post_status', $option)) {
        $option['post_status'] = null;
    }
    if (!array_key_exists('only_ontop', $option)) {
        $option['only_ontop'] = false;
    }
    if (!array_key_exists('only_not_ontop', $option)) {
        $option['only_not_ontop'] = false;
    }
    if (!array_key_exists('has_subcate', $option)) {
        $option['has_subcate'] = false;
    }
    if (!array_key_exists('is_related', $option)) {
        $option['is_related'] = false;
    }
    if ($option['is_related']) {
        $at = $zbp->GetPostByID($option['is_related']);
        $tags = $at->Tags;
        if (!$tags) {
            return array();
        }
        $count = ($count + 1);
    }

    $option2 = $option;
    unset($option2['post_type'], $option2['post_status'], $option2['only_ontop'], $option2['only_not_ontop']);
    unset($option2['has_subcate'], $option2['is_related'], $option2['order_by_metas']);

    $list = array();
    $post_type = null;
    $w = array();

    if ($option['post_type'] !== null) {
        $post_type = (int) $option['post_type'];
    }else{
        $post_type = 0;
    }
    $w[] = array('=', 'log_Type', $post_type);

    if ($option['post_status'] !== null) {
        $w[] = array('=', 'log_Status', (int) $option['post_status']);
    }

    if ($option['only_ontop'] == true) {
        $w[] = array('>', 'log_IsTop', 0);
    } elseif ($option['only_not_ontop'] == true) {
        $w[] = array('=', 'log_IsTop', 0);
    }

    if (!is_null($cate)) {
        $category = new Category();
        $category = $zbp->GetCategoryByID($cate);

        if ($category->ID > 0) {
            if (!$option['has_subcate']) {
                $w[] = array('=', 'log_CateID', $category->ID);
            } else {
                $arysubcate = array();
                $arysubcate[] = array('log_CateID', $category->ID);
                if (isset($zbp->categories_all[$category->ID])) {
                    foreach ($zbp->categories_all[$category->ID]->ChildrenCategories as $subcate) {
                        $arysubcate[] = array('log_CateID', $subcate->ID);
                    }
                }
                $w[] = array('array', $arysubcate);
            }
        } else {
            return array();
        }
    }

    if (!is_null($auth)) {
        $author = new Member();
        $author = $zbp->GetMemberByID($auth);

        if ($author->ID > 0) {
            $w[] = array('=', 'log_AuthorID', $author->ID);
        } else {
            return array();
        }
    }

    if (!is_null($date)) {
        $datetime = strtotime($date);
        if ($datetime) {
            $datetitle = str_replace(array('%y%', '%m%'), array(date('Y', $datetime), date('n', $datetime)), $zbp->lang['msg']['year_month']);
            $w[] = array('BETWEEN', 'log_PostTime', $datetime, strtotime('+1 month', $datetime));
        } else {
            return array();
        }
    }

    if (!is_null($tags)) {
        $tag = new Tag();
        if (is_array($tags)) {
            $ta = array();
            foreach ($tags as $t) {
                $ta[] = array('log_Tag', '%{' . $t->ID . '}%');
            }
            $w[] = array('array_like', $ta);
            unset($ta);
        } else {
            if (is_int($tags)) {
                $tag = $zbp->GetTagByID($tags);
            } else {
                $tag = $zbp->GetTagByAliasOrName($tags, $post_type);
            }
            if ($tag->ID > 0) {
                $w[] = array('LIKE', 'log_Tag', '%{' . $tag->ID . '}%');
            } else {
                return array();
            }
        }
    }

    if (is_string($search)) {
        $search = trim($search);
        if ($search !== '') {
            $w[] = array('search', 'log_Content', 'log_Intro', 'log_Title', $search);
        } else {
            return array();
        }
    }

    $select = '';
    $order = array('log_PostTime' => 'DESC');

    foreach ($GLOBALS['hooks']['Filter_Plugin_LargeData_GetList'] as $fpname => &$fpsignal) {
        $fpreturn = $fpname($select, $w, $order, $count, $option2);
    }

    $list = $zbp->GetPostList($select, $w, $order, $count, $option2);

    if ($option['is_related']) {
        foreach ($list as $k => $a) {
            if ($a->ID == $option['is_related']) {
                unset($list[$k]);
            }
        }
        if (count($list) == $count) {
            array_pop($list);
        }
    }
    if (isset($option['order_by_metas'])) { //从meta里的值排序
        if (is_array($option['order_by_metas'])) {
            $orderkey = key($option['order_by_metas']);
            $order = current($option['order_by_metas']);
        } else {
            $orderkey = current($option['order_by_metas']);
            $order = 'asc';
        }
        $orderarray = array();
        foreach ($list as $key => $value) {
            $orderarray[$key] = $value->Metas->$orderkey;
        }
        if (strtolower($order) == 'desc') {
            arsort($orderarray);
        } else {
            asort($orderarray);
        }
        $newlist = array();
        foreach ($orderarray as $key => $value) {
            $newlist[] = $list[$key];
        }
        $list = $newlist;
    }


    foreach ($GLOBALS['hooks']['Filter_Plugin_GetList_Result'] as $fpname => &$fpsignal) {
        $fpreturn = $fpname($list);
    }

    return $list;
}

//###############################################################################################################

/**
 * ViewIndex,首页，搜索页，feed页的主函数.
 *
 * @api Filter_Plugin_ViewIndex_Begin
 *
 * @throws Exception
 *
 * @return mixed
 */
function ViewIndex()
{
    global $zbp, $action;

    if (IS_IIS && isset($_GET['rewrite']) && isset($_GET['full_uri'])) {
        //对iis + rewrite进行修正
        $uri_array = parse_url($_GET['full_uri']);
        if (isset($uri_array['query'])) {
            parse_str($uri_array['query'], $uri_query);
            $_GET = array_merge($_GET, $uri_query);
            $_REQUEST = array_merge($_REQUEST, $uri_query);
        }
        unset($uri_array, $uri_query);
    }

    $url = $zbp->currenturl;
    foreach ($GLOBALS['hooks']['Filter_Plugin_ViewIndex_Begin'] as $fpname => &$fpsignal) {
        $fpreturn = $fpname($url);
        if ($fpsignal == PLUGIN_EXITSIGNAL_RETURN) {
            $fpsignal = PLUGIN_EXITSIGNAL_NONE;

            return $fpreturn;
        }
    }

    switch ($action) {
        case 'feed':
            ViewFeed();
            break;
        case 'search':
            ViewSearch();
            break;
        case '':
        default:
            if ($url == $zbp->cookiespath || $url == $zbp->cookiespath . 'index.php') {
                ViewList(null, null, null, null, null);
            } elseif (($zbp->option['ZC_STATIC_MODE'] == 'ACTIVE' || isset($_GET['rewrite']))
                && (isset($_GET['id']) || isset($_GET['alias']))
            ) {
                ViewPost(GetVars('id', 'GET'), GetVars('alias', 'GET'));
            } elseif (($zbp->option['ZC_STATIC_MODE'] == 'ACTIVE' || isset($_GET['rewrite']))
                && (isset($_GET['page']) || isset($_GET['cate']) || isset($_GET['auth']) || isset($_GET['date']) || isset($_GET['tags']))
            ) {
                ViewList(GetVars('page', 'GET'), GetVars('cate', 'GET'), GetVars('auth', 'GET'), GetVars('date', 'GET'), GetVars('tags', 'GET'));
            } else {
                ViewAuto($url);
            }
    }

    return false;
}

/**
 * 显示RSS2Feed.
 *
 * @api Filter_Plugin_ViewFeed_Begin
 */
function ViewFeed()
{
    global $zbp;

    foreach ($GLOBALS['hooks']['Filter_Plugin_ViewFeed_Begin'] as $fpname => &$fpsignal) {
        $fpreturn = $fpname();
        if ($fpsignal == PLUGIN_EXITSIGNAL_RETURN) {
            $fpsignal = PLUGIN_EXITSIGNAL_NONE;

            return $fpreturn;
        }
    }

    if (!$zbp->CheckRights($GLOBALS['action'])) {
        Http404();
        die;
    }

    $rss2 = new Rss2($zbp->name, $zbp->host, $zbp->subname);

    $w = array(array('=', 'log_Status', 0));

    if (GetVars('cate', 'GET') != null) {
        $w[] = array('=', 'log_CateID', (int) GetVars('cate', 'GET'));
    } elseif (GetVars('auth', 'GET') != null) {
        $w[] = array('=', 'log_AuthorID', (int) GetVars('auth', 'GET'));
    } elseif (GetVars('date', 'GET') != null) {
        $d = strtotime(GetVars('date', 'GET'));
        if (strrpos(GetVars('date', 'GET'), '-') !== strpos(GetVars('date', 'GET'), '-')) {
            $w[] = array('BETWEEN', 'log_PostTime', $d, strtotime('+1 day', $d));
        } else {
            $w[] = array('BETWEEN', 'log_PostTime', $d, strtotime('+1 month', $d));
        }
    } elseif (GetVars('tags', 'GET') != null) {
        $w[] = array('LIKE', 'log_Tag', '%{' . (int) GetVars('tags', 'GET') . '}%');
    }

    foreach ($GLOBALS['hooks']['Filter_Plugin_ViewFeed_Core'] as $fpname => &$fpsignal) {
        $fpname($w);
    }

    $articles = $zbp->GetArticleList(
        '*',
        $w,
        array('log_PostTime' => 'DESC'),
        $zbp->option['ZC_RSS2_COUNT'],
        null
    );

    foreach ($articles as $article) {
        $rss2->addItem($article->Title, $article->Url, ($zbp->option['ZC_RSS_EXPORT_WHOLE'] == true ? $article->Content : $article->Intro), $article->PostTime);
    }

    foreach ($GLOBALS['hooks']['Filter_Plugin_ViewFeed_End'] as $fpname => &$fpsignal) {
        $fpreturn = $fpname($rss2);
        if ($fpsignal == PLUGIN_EXITSIGNAL_RETURN) {
            $fpsignal = PLUGIN_EXITSIGNAL_NONE;

            return $fpreturn;
        }
    }

    header("Content-type:text/xml; Charset=utf-8");

    echo $rss2->saveXML();

    return true;
}

/**
 * 展示搜索结果.
 *
 * @api Filter_Plugin_ViewSearch_Begin
 * @api Filter_Plugin_ViewPost_Template
 *
 * @throws Exception
 *
 * @return mixed
 */
function ViewSearch()
{
    global $zbp;

    foreach ($GLOBALS['hooks']['Filter_Plugin_ViewSearch_Begin'] as $fpname => &$fpsignal) {
        $fpreturn = $fpname();
        if ($fpsignal == PLUGIN_EXITSIGNAL_RETURN) {
            $fpsignal = PLUGIN_EXITSIGNAL_NONE;

            return $fpreturn;
        }
    }

    if (!$zbp->CheckRights($GLOBALS['action'])) {
        Redirect('./');
    }

    $q = trim(htmlspecialchars(GetVars('q', 'GET')));
    $page = GetVars('page', 'GET');
    $page = (int) $page == 0 ? 1 : (int) $page;

    $article = new Post();
    $article->ID = 0;
    $article->Title = $zbp->langs->msg->search . '&nbsp;&quot;<span>' . $q . '</span>&quot;';
    $article->IsLock = true;
    $article->Type = ZC_POST_TYPE_PAGE;

    if ($zbp->template->hasTemplate('search')) {
        $article->Template = 'search';
    }

    $w = array();
    $w[] = array('=', 'log_Type', '0');
    if ($q) {
        $w[] = array('search', 'log_Content', 'log_Intro', 'log_Title', $q);
    } else {
        Redirect('./');
    }

    if (!($zbp->CheckRights('ArticleAll') && $zbp->CheckRights('PageAll'))) {
        $w[] = array('=', 'log_Status', 0);
    }
    $order = array('log_PostTime' => 'DESC');

    $pagebar = new Pagebar($zbp->option['ZC_SEARCH_REGEX'], true);
    $pagebar->PageCount = $zbp->searchcount;
    $pagebar->PageNow = $page;
    $pagebar->PageBarCount = $zbp->pagebarcount;
    $pagebar->UrlRule->Rules['{%page%}'] = $page;
    $pagebar->UrlRule->Rules['{%q%}'] = rawurlencode($q);

    foreach ($GLOBALS['hooks']['Filter_Plugin_ViewSearch_Core'] as $fpname => &$fpsignal) {
        $fpname($q, $page, $w, $pagebar, $order);
    }

    $array = $zbp->GetArticleList(
        '',
        $w,
        $order,
        array(($pagebar->PageNow - 1) * $pagebar->PageCount, $pagebar->PageCount),
        array('pagebar' => $pagebar),
        false
    );

    $results = array();

    foreach ($array as $a) {
        $r = new Post();
        $r->LoadInfoByDataArray($a->GetData());
        $article->Content .= '<p><a href="' . $a->Url . '">' . str_replace($q, '<strong>' . $q . '</strong>', $a->Title) . '</a><br/>';
        $s = strip_tags($a->Intro) . ' ' . strip_tags($a->Content);
        $i = Zbp_Strpos($s, $q, 0);
        if ($i !== false) {
            if ($i > 50) {
                $t = SubStrUTF8_Start($s, ($i - 50), 100);
            } else {
                $t = SubStrUTF8_Start($s, 0, 100);
            }
            $article->Content .= str_replace($q, '<strong>' . $q . '</strong>', $t) . '<br/>';
            $r->Intro = str_replace($q, '<strong>' . $q . '</strong>', $t);
            $r->Content = $a->Content;
        } else {
            $s = strip_tags($a->Title);
            $i = Zbp_Strpos($s, $q, 0);
            if ($i > 50) {
                $t = SubStrUTF8_Start($s, ($i - 50), 100);
            } else {
                $t = SubStrUTF8_Start($s, 0, 100);
            }
            $article->Content .= str_replace($q, '<strong>' . $q . '</strong>', $t) . '<br/>';
            $r->Intro = str_replace($q, '<strong>' . $q . '</strong>', $t);
            $r->Content = $a->Content;
        }
        $r->Title = str_replace($q, '<strong>' . $q . '</strong>', $r->Title);
        $article->Content .= '<a href="' . $a->Url . '">' . $a->Url . '</a><br/></p>';
        $results[] = $r;
    }

    $zbp->header .= '<meta name="robots" content="noindex,follow" />' . "\r\n";
    $zbp->template->SetTags('title', str_replace(array('<span>', '</span>'), '', $article->Title));
    $zbp->template->SetTags('article', $article);
    $zbp->template->SetTags('search', $q);
    $zbp->template->SetTags('page', $page);
    $zbp->template->SetTags('pagebar', $pagebar);
    $zbp->template->SetTags('comments', array());
    $zbp->template->SetTags('issearch', true);

    //1.6新加设置，可以让搜索变为列表模式运行
    //1.7强制指定搜索模板为search或是index
    $zbp->template->SetTags('type', 'search'); //1.6统一改为search
    //if (isset($zbp->option['ZC_SEARCH_TYPE']) && $zbp->option['ZC_SEARCH_TYPE'] == 'list') {
    $zbp->template->SetTags('articles', $results);
    if ($zbp->template->HasTemplate('search')) {
        $zbp->template->SetTemplate('search');
    } else {
        $zbp->template->SetTemplate('index');
    }
    //} else {
        //$zbp->template->SetTags('articles', $array);
        //$zbp->template->SetTemplate($article->Template);
    //}

    foreach ($GLOBALS['hooks']['Filter_Plugin_ViewSearch_Template'] as $fpname => &$fpsignal) {
        $fpreturn = $fpname($zbp->template);
        if ($fpsignal == PLUGIN_EXITSIGNAL_RETURN) {
            $fpsignal = PLUGIN_EXITSIGNAL_NONE;

            return $fpreturn;
        }
    }

    $zbp->template->Display();

    return true;
}

//###############################################################################################################

/**
 * 根据Rewrite_url规则显示页面.
 *
 * @param string $inpurl 页面url
 *
 * @api Filter_Plugin_ViewAuto_Begin
 * @api Filter_Plugin_ViewAuto_End
 *
 * @throws Exception
 *
 * @return null|string
 */
function ViewAuto($inpurl)
{
    global $zbp;

    $url = GetValueInArray(explode('?', $inpurl), '0');

    if ($zbp->cookiespath === substr($url, 0, strlen($zbp->cookiespath))) {
        $url = substr($url, strlen($zbp->cookiespath));
    }

    foreach ($GLOBALS['hooks']['Filter_Plugin_ViewAuto_Begin'] as $fpname => &$fpsignal) {
        $fpreturn = $fpname($inpurl, $url);
        if ($fpsignal == PLUGIN_EXITSIGNAL_RETURN) {
            $fpsignal = PLUGIN_EXITSIGNAL_NONE;

            return $fpreturn;
        }
    }

    if (IS_IIS && isset($_GET['rewrite'])) {
        //iis+httpd.ini下如果存在真实文件
        $realurl = $zbp->path . urldecode($url);
        if (is_readable($realurl) && is_file($realurl) && !preg_match('/\.php$/', $realurl)) {
            die(file_get_contents($realurl));
        }
        unset($realurl);
    }

    $url = urldecode($url);

    if ($url == '' || $url == '/' || $url == 'index.php') {
        ViewList(null, null, null, null, null);

        return;
    }

    if ($zbp->option['ZC_STATIC_MODE'] == 'REWRITE') {
        $r = UrlRule::OutputUrlRegEx($zbp->option['ZC_INDEX_REGEX'], 'index');
        $m = array();
        if (preg_match($r, $url, $m) == 1) {
            ViewList($m['page'], null, null, null, null, true);

            return;
        }

        $r = UrlRule::OutputUrlRegEx($zbp->option['ZC_DATE_REGEX'], 'date', false);
        $m = array();
        if (preg_match($r, $url, $m) == 1) {
            isset($m['page']) ? null : $m['page'] = 0;
            $result = ViewList($m['page'], null, null, $m, null, true);
            if ($result == true) {
                return;
            }
        }

        $r = UrlRule::OutputUrlRegEx($zbp->option['ZC_DATE_REGEX'], 'date', true);
        $m = array();
        if (preg_match($r, $url, $m) == 1) {
            $result = ViewList($m['page'], null, null, $m, null, true);
            if ($result == true) {
                return;
            }
        }

        $r = UrlRule::OutputUrlRegEx($zbp->option['ZC_AUTHOR_REGEX'], 'auth', false);
        $m = array();
        if (preg_match($r, $url, $m) == 1) {
            isset($m['page']) ? null : $m['page'] = 0;
            $result = ViewList($m['page'], null, $m, null, null, true);
            if ($result == true) {
                return;
            }
        }

        $r = UrlRule::OutputUrlRegEx($zbp->option['ZC_AUTHOR_REGEX'], 'auth', true);
        $m = array();
        if (preg_match($r, $url, $m) == 1) {
            $result = ViewList($m['page'], null, $m, null, null, true);
            if ($result == true) {
                return;
            }
        }

        $r = UrlRule::OutputUrlRegEx($zbp->option['ZC_TAGS_REGEX'], 'tags', false);
        $m = array();
        if (preg_match($r, $url, $m) == 1) {
            isset($m['page']) ? null : $m['page'] = 0;
            $result = ViewList($m['page'], null, null, null, $m, true);
            if ($result == true) {
                return;
            }
        }

        $r = UrlRule::OutputUrlRegEx($zbp->option['ZC_TAGS_REGEX'], 'tags', true);
        $m = array();
        if (preg_match($r, $url, $m) == 1) {
            $result = ViewList($m['page'], null, null, null, $m, true);
            if ($result == true) {
                return;
            }
        }

        $r = UrlRule::OutputUrlRegEx($zbp->option['ZC_CATEGORY_REGEX'], 'cate', false);
        $m = array();
        if (preg_match($r, $url, $m) == 1) {
            isset($m['page']) ? null : $m['page'] = 0;
            $result = ViewList($m['page'], $m, null, null, null, true);
            if ($result == true) {
                return;
            }
        }

        $r = UrlRule::OutputUrlRegEx($zbp->option['ZC_CATEGORY_REGEX'], 'cate', true);
        $m = array();
        if (preg_match($r, $url, $m) == 1) {
            $result = ViewList($m['page'], $m, null, null, null, true);
            if ($result == true) {
                return;
            }
        }

        $r = UrlRule::OutputUrlRegEx($zbp->option['ZC_ARTICLE_REGEX'], 'article');
        $m = array();
        if (preg_match($r, $url, $m) == 1) {
            $result = ViewPost($m, null, true);
            if ($result == false) {
                $zbp->ShowError(2, __FILE__, __LINE__);
            }

            return;
        }

        $r = UrlRule::OutputUrlRegEx($zbp->option['ZC_PAGE_REGEX'], 'page');
        $m = array();
        if (preg_match($r, $url, $m) == 1) {
            $result = ViewPost($m, null, true);
            if ($result == false) {
                $zbp->ShowError(2, __FILE__, __LINE__);
            }

            return;
        }
    }

    foreach ($GLOBALS['hooks']['Filter_Plugin_ViewAuto_End'] as $fpname => &$fpsignal) {
        $fpreturn = $fpname($url);
        if ($fpsignal == PLUGIN_EXITSIGNAL_RETURN) {
            $fpsignal = PLUGIN_EXITSIGNAL_NONE;

            return $fpreturn;
        }
    }

    if (isset($zbp->option['ZC_COMPATIBLE_ASP_URL']) && ($zbp->option['ZC_COMPATIBLE_ASP_URL'] == true)) {
        if (isset($_GET['id']) || isset($_GET['alias'])) {
            ViewPost(GetVars('id', 'GET'), GetVars('alias', 'GET'));

            return;
        } elseif (isset($_GET['page']) || isset($_GET['cate']) || isset($_GET['auth']) || isset($_GET['date']) || isset($_GET['tags'])) {
            ViewList(GetVars('page', 'GET'), GetVars('cate', 'GET'), GetVars('auth', 'GET'), GetVars('date', 'GET'), GetVars('tags', 'GET'));

            return;
        }
    }

    $zbp->ShowError(2, __FILE__, __LINE__);

    return false;
}

/**
 * 显示列表页面.
 *
 * @param int   $page
 * @param mixed $cate
 * @param mixed $auth
 * @param mixed $date
 * @param mixed $tags      tags列表
 * @param bool  $isrewrite 是否启用urlrewrite
 *
 * @api Filter_Plugin_ViewList_Begin
 * @api Filter_Plugin_ViewList_Template
 *
 * @throws Exception
 *
 * @return string
 */
function ViewList($page, $cate, $auth, $date, $tags, $isrewrite = false)
{
    global $zbp;

    foreach ($GLOBALS['hooks']['Filter_Plugin_ViewList_Begin'] as $fpname => &$fpsignal) {
        $fpargs = func_get_args();
        $fpreturn = call_user_func_array($fpname, $fpargs);
        if ($fpsignal == PLUGIN_EXITSIGNAL_RETURN) {
            $fpsignal = PLUGIN_EXITSIGNAL_NONE;

            return $fpreturn;
        }
    }

    $type = 'index';
    if ($cate !== null) {
        $type = 'category';
    }

    if ($auth !== null) {
        $type = 'author';
    }

    if ($date !== null) {
        $type = 'date';
    }

    if ($tags !== null) {
        $type = 'tag';
    }

    $category = null;
    $author = null;
    $datetime = null;
    $tag = null;

    $w = array();
    //$w[] = array('=', 'log_IsTop', 0);
    $w[] = array('=', 'log_Status', 0);

    $page = (int) $page == 0 ? 1 : (int) $page;

    $articles = array();
    $articles_top = array();

    switch ($type) {
            //#######################################################################################################
        case 'index':
            $pagebar = new Pagebar($zbp->option['ZC_INDEX_REGEX'], true, true);
            $pagebar->Count = $zbp->cache->normal_article_nums;
            $template = $zbp->option['ZC_INDEX_DEFAULT_TEMPLATE'];
            if ($page == 1) {
                $zbp->title = $zbp->subname;
            } else {
                $zbp->title = str_replace('%num%', $page, $zbp->lang['msg']['number_page']);
            }
            break;
            //#######################################################################################################
        case 'category':
            $pagebar = new Pagebar($zbp->option['ZC_CATEGORY_REGEX']);
            $category = new Category();

            if (!is_array($cate)) {
                $cateId = $cate;
                $cate = array();
                if (strpos($zbp->option['ZC_CATEGORY_REGEX'], '{%id%}') !== false) {
                    $cate['id'] = $cateId;
                }
                if (strpos($zbp->option['ZC_CATEGORY_REGEX'], '{%alias%}') !== false) {
                    $cate['alias'] = $cateId;
                }
            }
            if (isset($cate['id'])) {
                $category = $zbp->GetCategoryByID($cate['id']);
            } else {
                $category = $zbp->GetCategoryByAlias($cate['alias']);
            }

            if ($category->ID == '') {
                if ($isrewrite == true) {
                    return false;
                }

                $zbp->ShowError(2, __FILE__, __LINE__);
            }
            if ($page == 1) {
                $zbp->title = $category->Name;
            } else {
                $zbp->title = $category->Name . ' ' . str_replace('%num%', $page, $zbp->lang['msg']['number_page']);
            }
            $template = $category->Template;

            if (!$zbp->option['ZC_DISPLAY_SUBCATEGORYS']) {
                $w[] = array('=', 'log_CateID', $category->ID);
                $pagebar->Count = $category->Count;
            } else {
                $arysubcate = array();
                $arysubcate[] = array('log_CateID', $category->ID);
                if (isset($zbp->categories[$category->ID])) {
                    foreach ($zbp->categories[$category->ID]->ChildrenCategories as $subcate) {
                        $arysubcate[] = array('log_CateID', $subcate->ID);
                    }
                }
                $w[] = array('array', $arysubcate);
            }

            $pagebar->UrlRule->Rules['{%id%}'] = $category->ID;
            $pagebar->UrlRule->Rules['{%alias%}'] = $category->Alias == '' ? rawurlencode($category->Name) : $category->Alias;
            break;
            //#######################################################################################################
        case 'author':
            $pagebar = new Pagebar($zbp->option['ZC_AUTHOR_REGEX']);
            $author = new Member();

            if (!is_array($auth)) {
                $authId = $auth;
                $auth = array();
                if (strpos($zbp->option['ZC_AUTHOR_REGEX'], '{%id%}') !== false) {
                    $auth['id'] = $authId;
                }
                if (strpos($zbp->option['ZC_AUTHOR_REGEX'], '{%alias%}') !== false) {
                    $auth['alias'] = $authId;
                }
            }
            if (isset($auth['id'])) {
                /* @var Member $author */
                $author = $zbp->GetMemberByID($auth['id']);
            } else {
                /* @var Member $author */
                $author = $zbp->GetMemberByNameOrAlias($auth['alias']);
            }

            if ($author->ID == '') {
                if ($isrewrite) {
                    return false;
                }

                $zbp->ShowError(2, __FILE__, __LINE__);
            }
            if ($page == 1) {
                $zbp->title = $author->StaticName;
            } else {
                $zbp->title = $author->StaticName . ' ' . str_replace('%num%', $page, $zbp->lang['msg']['number_page']);
            }
            $template = $author->Template;
            $w[] = array('=', 'log_AuthorID', $author->ID);
            //$pagebar->Count = $author->Articles;
            $pagebar->UrlRule->Rules['{%id%}'] = $author->ID;
            $pagebar->UrlRule->Rules['{%alias%}'] = $author->Alias == '' ? rawurlencode($author->Name) : $author->Alias;
            break;
            //#######################################################################################################
        case 'date':
            $pagebar = new Pagebar($zbp->option['ZC_DATE_REGEX']);

            if (!is_array($date)) {
                $datetime = $date;
            } else {
                $datetime = $date['date'];
            }

            $dateregex_ymd = '/[0-9]{1,4}-[0-9]{1,2}-[0-9]{1,2}/i';
            $dateregex_ym = '/[0-9]{1,4}-[0-9]{1,2}/i';

            if (preg_match($dateregex_ymd, $datetime) == 0 && preg_match($dateregex_ym, $datetime) == 0) {
                return false;
            }
            $datetime_txt = $datetime;
            $datetime = strtotime($datetime);
            if ($datetime == false) {
                return false;
            }

            if (preg_match($dateregex_ymd, $datetime_txt) != 0 && isset($zbp->lang['msg']['year_month_day'])) {
                $datetitle = str_replace(array('%y%', '%m%', '%d%'), array(date('Y', $datetime), date('n', $datetime), date('j', $datetime)), $zbp->lang['msg']['year_month_day']);
            } else {
                $datetitle = str_replace(array('%y%', '%m%'), array(date('Y', $datetime), date('n', $datetime)), $zbp->lang['msg']['year_month']);
            }

            if ($page == 1) {
                $zbp->title = $datetitle;
            } else {
                $zbp->title = $datetitle . ' ' . str_replace('%num%', $page, $zbp->lang['msg']['number_page']);
            }

            $zbp->modulesbyfilename['calendar']->Content = ModuleBuilder::Calendar(date('Y', $datetime) . '-' . date('n', $datetime));

            $template = $zbp->option['ZC_INDEX_DEFAULT_TEMPLATE'];

            if (preg_match($dateregex_ymd, $datetime_txt) != 0) {
                $w[] = array('BETWEEN', 'log_PostTime', $datetime, strtotime('+1 day', $datetime));
                $pagebar->UrlRule->Rules['{%date%}'] = date('Y-n-j', $datetime);
            } else {
                $w[] = array('BETWEEN', 'log_PostTime', $datetime, strtotime('+1 month', $datetime));
                $pagebar->UrlRule->Rules['{%date%}'] = date('Y-n', $datetime);
            }

            $datetime = Metas::ConvertArray(getdate($datetime));
            break;
            //#######################################################################################################
        case 'tag':
            $pagebar = new Pagebar($zbp->option['ZC_TAGS_REGEX']);
            $tag = new Tag();

            if (!is_array($tags)) {
                $tagId = $tags;
                $tags = array();
                if (strpos($zbp->option['ZC_TAGS_REGEX'], '{%id%}') !== false) {
                    $tags['id'] = $tagId;
                }
                if (strpos($zbp->option['ZC_TAGS_REGEX'], '{%alias%}') !== false) {
                    $tags['alias'] = $tagId;
                }
            }
            if (isset($tags['id'])) {
                $tag = $zbp->GetTagByID($tags['id']);
            } else {
                $tag = $zbp->GetTagByAliasOrName($tags['alias']);
            }

            if ($tag->ID == 0) {
                if ($isrewrite == true) {
                    return false;
                }

                $zbp->ShowError(2, __FILE__, __LINE__);
            }

            if ($page == 1) {
                $zbp->title = $tag->Name;
            } else {
                $zbp->title = $tag->Name . ' ' . str_replace('%num%', $page, $zbp->lang['msg']['number_page']);
            }

            $template = $tag->Template;
            $w[] = array('LIKE', 'log_Tag', '%{' . $tag->ID . '}%');
            $pagebar->UrlRule->Rules['{%id%}'] = $tag->ID;
            $pagebar->UrlRule->Rules['{%alias%}'] = $tag->Alias == '' ? rawurlencode($tag->Name) : $tag->Alias;
            break;
        default:
            throw new Exception('Unknown type');
    }

    $pagebar->PageCount = $zbp->displaycount;
    $pagebar->PageNow = $page;
    $pagebar->PageBarCount = $zbp->pagebarcount;
    $pagebar->UrlRule->Rules['{%page%}'] = $page;

    foreach ($GLOBALS['hooks']['Filter_Plugin_ViewList_Core'] as $fpname => &$fpsignal) {
        $fpname($type, $page, $category, $author, $datetime, $tag, $w, $pagebar, $template);
    }

    if ($zbp->option['ZC_LISTONTOP_TURNOFF'] == false) {
        $articles_top_notorder = $zbp->GetTopPost(ZC_POST_TYPE_ARTICLE);
        foreach ($articles_top_notorder as $articles_top_notorder_post) {
            if ($articles_top_notorder_post->TopType == 'global') {
                $articles_top[] = $articles_top_notorder_post;
            }
        }

        if ($type == 'index' && $page == 1) {
            foreach ($articles_top_notorder as $articles_top_notorder_post) {
                if ($articles_top_notorder_post->TopType == 'index') {
                    $articles_top[] = $articles_top_notorder_post;
                }
            }
        }

        if ($type == 'category') {
            foreach ($articles_top_notorder as $articles_top_notorder_post) {
                if ($articles_top_notorder_post->TopType == 'category' && $articles_top_notorder_post->CateID == $category->ID) {
                    $articles_top[] = $articles_top_notorder_post;
                }
            }
        }
    }

    $select = '';
    $order = array('log_PostTime' => 'DESC');
    $limit = array(($pagebar->PageNow - 1) * $pagebar->PageCount, $pagebar->PageCount);
    $option = array('pagebar' => $pagebar);

    foreach ($GLOBALS['hooks']['Filter_Plugin_LargeData_Article'] as $fpname => &$fpsignal) {
        $fpreturn = $fpname($select, $w, $order, $limit, $option, $type);
    }

    $articles = $zbp->GetArticleList(
        $select,
        $w,
        $order,
        $limit,
        $option,
        true
    );

    //处理原置顶文章回到正常时的属性先改为0
    foreach ($articles as $key3 => $value3) {
        if ($value3->IsTop > 0) {
            $value3->IsTop = 0;
        }
    }
    foreach ($articles_top as $key1 => $value1) {
        foreach ($articles as $key2 => $value2) {
            if ($value1->ID == $value2->ID) {
                unset($articles[$key2]);
                break;
            }
        }
    }

    if (count($articles) <= 0 && $page > 1) {
        $zbp->ShowError(2, __FILE__, __LINE__);
    }

    $zbp->LoadMembersInList($articles_top);
    $zbp->LoadMembersInList($articles);

    $zbp->template->SetTags('title', $zbp->title);
    $zbp->template->SetTags('articles', array_merge($articles_top, $articles));
    if ($pagebar->PageAll == 0) {
        $pagebar = null;
    }

    $zbp->template->SetTags('pagebar', $pagebar);
    $zbp->template->SetTags('type', $type);
    $zbp->template->SetTags('page', $page);

    $zbp->template->SetTags('date', $datetime);
    $zbp->template->SetTags('tag', $tag);
    $zbp->template->SetTags('author', $author);
    $zbp->template->SetTags('category', $category);

    if ($zbp->template->hasTemplate($template)) {
        $zbp->template->SetTemplate($template);
    } else {
        $zbp->template->SetTemplate('index');
    }

    foreach ($GLOBALS['hooks']['Filter_Plugin_ViewList_Template'] as $fpname => &$fpsignal) {
        $fpreturn = $fpname($zbp->template);
        if ($fpsignal == PLUGIN_EXITSIGNAL_RETURN) {
            $fpsignal = PLUGIN_EXITSIGNAL_NONE;

            return $fpreturn;
        }
    }

    $zbp->template->Display();

    return true;
}

/**
 * 显示文章.
 *
 * @param array|int|string $object         文章ID/ ID/别名对象
 * @param string           $theSecondParam （如果有的话）文章别名
 * @param bool             $enableRewrite  是否启用urlrewrite
 *
 * @throws Exception
 *
 * @return string
 */
function ViewPost($object, $theSecondParam, $enableRewrite = false)
{
    global $zbp;

    if (is_array($object)) {
        $id = isset($object['id']) ? $object['id'] : null;
        $alias = isset($object['alias']) ? $object['alias'] : null;
    } else {
        $id = $object;
        $alias = $theSecondParam;
        $object = array('id' => $object);
        $object[0] = $id;
        $object['id'] = $id;
    }

    foreach ($GLOBALS['hooks']['Filter_Plugin_ViewPost_Begin'] as $fpname => &$fpsignal) {
        $fpargs = array($object, $theSecondParam, $enableRewrite);
        $fpargs[0] = $id;
        $fpargs[1] = $alias;
        $fpreturn = call_user_func_array($fpname, $fpargs);
        if ($fpsignal == PLUGIN_EXITSIGNAL_RETURN) {
            $fpsignal = PLUGIN_EXITSIGNAL_NONE;

            return $fpreturn;
        }
    }

    $select = '';
    $w = array();
    $order = null;
    $limit = 1;
    $option = null;

    if ($id !== null) {
        if (function_exists('ctype_digit') && !ctype_digit((string) $id)) {
            $zbp->ShowError(3, __FILE__, __LINE__);
        }

        $w[] = array('=', 'log_ID', $id);
    } elseif ($alias !== null) {
        if ($zbp->option['ZC_POST_ALIAS_USE_ID_NOT_TITLE'] == false) {
            $w[] = array('array', array(array('log_Alias', $alias), array('log_Title', $alias)));
        } else {
            $w[] = array('array', array(array('log_Alias', $alias), array('log_ID', $alias)));
        }
    } else {
        $zbp->ShowError(2, __FILE__, __LINE__);
        exit;
    }

    if (!($zbp->CheckRights('ArticleAll') && $zbp->CheckRights('PageAll'))) {
        $w[] = array('=', 'log_Status', 0);
    }

    foreach ($GLOBALS['hooks']['Filter_Plugin_ViewPost_Core'] as $fpname => &$fpsignal) {
        $fpname($select, $w, $order, $limit, $option);
    }

    $articles = $zbp->GetPostList($select, $w, $order, $limit, $option);
    if (count($articles) == 0) {
        if ($enableRewrite == true) {
            return false;
        }

        $zbp->ShowError(2, __FILE__, __LINE__);
    }

    $article = $articles[0];

    if ($enableRewrite && !(stripos(urldecode($article->Url), $object[0]) !== false)) {
        $zbp->ShowError(2, __FILE__, __LINE__);
        exit;
    }

    if ($article->Type == 0) {
        $zbp->LoadTagsByIDString($article->Tag);
    }

    if (isset($zbp->option['ZC_VIEWNUMS_TURNOFF']) && $zbp->option['ZC_VIEWNUMS_TURNOFF'] == false) {
        $article->ViewNums += 1;
        $sql = $zbp->db->sql->Update($zbp->table['Post'], array('log_ViewNums' => $article->ViewNums), array(array('=', 'log_ID', $article->ID)));
        $zbp->db->Update($sql);
    }

    $pagebar = new Pagebar('javascript:zbp.comment.get(\'' . $article->ID . '\',\'{%page%}\');', false);
    $pagebar->PageCount = $zbp->commentdisplaycount;
    $pagebar->PageNow = 1;
    $pagebar->PageBarCount = $zbp->pagebarcount;
    //$pagebar->Count = $article->CommNums;

    if ($zbp->option['ZC_COMMENT_TURNOFF']) {
        $article->IsLock = true;
    }

    $comments = array();

    if (!$article->IsLock && $zbp->socialcomment == null) {
        $comments = $zbp->GetCommentList(
            '*',
            array(
                array('=', 'comm_LogID', $article->ID),
                array('=', 'comm_RootID', 0),
                array('=', 'comm_IsChecking', 0),
            ),
            array('comm_ID' => ($zbp->option['ZC_COMMENT_REVERSE_ORDER'] ? 'DESC' : 'ASC')),
            array(($pagebar->PageNow - 1) * $pagebar->PageCount, $pagebar->PageCount),
            array('pagebar' => $pagebar)
        );
        $rootid = array();
        foreach ($comments as &$comment) {
            $rootid[] = $comment->ID;
        }
        $comments2 = $zbp->GetCommentList(
            '*',
            array(
                array('=', 'comm_LogID', $article->ID),
                array('IN', 'comm_RootID', $rootid),
                array('=', 'comm_IsChecking', 0),
            ),
            array('comm_ID' => ($zbp->option['ZC_COMMENT_REVERSE_ORDER'] ? 'DESC' : 'ASC')),
            null,
            null
        );
        $floorid = (($pagebar->PageNow - 1) * $pagebar->PageCount);
        foreach ($comments as &$comment) {
            $floorid += 1;
            $comment->FloorID = $floorid;
            $comment->Content = FormatString($comment->Content, '[enter]');
            if (strpos($zbp->template->templates['comment'], 'id="AjaxComment') === false) {
                $comment->Content .= '<label id="AjaxComment' . $comment->ID . '"></label>';
            }
        }
        foreach ($comments2 as &$comment) {
            $comment->Content = FormatString($comment->Content, '[enter]');
            if (strpos($zbp->template->templates['comment'], 'id="AjaxComment') === false) {
                $comment->Content .= '<label id="AjaxComment' . $comment->ID . '"></label>';
            }
        }
    }

    $zbp->LoadMembersInList($comments);

    $zbp->template->SetTags('title', ($article->Status == 0 ? '' : '[' . $zbp->lang['post_status_name'][$article->Status] . ']') . $article->Title);
    $zbp->template->SetTags('article', $article);
    $zbp->template->SetTags('type', $article->TypeName);
    $zbp->template->SetTags('page', 1);
    if ($pagebar->PageAll == 0 || $pagebar->PageAll == 1) {
        $pagebar = null;
    }

    $zbp->template->SetTags('pagebar', $pagebar);
    $zbp->template->SetTags('comments', $comments);

    if ($zbp->template->hasTemplate($article->Template)) {
        $zbp->template->SetTemplate($article->Template);
    } else {
        $zbp->template->SetTemplate('single');
    }

    foreach ($GLOBALS['hooks']['Filter_Plugin_ViewPost_Template'] as $fpname => &$fpsignal) {
        $fpreturn = $fpname($zbp->template);
        if ($fpsignal == PLUGIN_EXITSIGNAL_RETURN) {
            $fpsignal = PLUGIN_EXITSIGNAL_NONE;

            return $fpreturn;
        }
    }

    $zbp->template->Display();

    return true;
}

/**
 * 显示文章下评论列表.
 *
 * @param int $postid 文章ID
 * @param int $page   页数
 *
 * @throws Exception
 *
 * @return bool
 */
function ViewComments($postid, $page)
{
    global $zbp;

    $post = new Post();
    $post = $zbp->GetPostByID($postid);
    $page = $page == 0 ? 1 : $page;
    $template = 'comments';

    $pagebar = new Pagebar('javascript:zbp.comment.get(\'' . $post->ID . '\',\'{%page%}\');');
    $pagebar->PageCount = $zbp->commentdisplaycount;
    $pagebar->PageNow = $page;
    $pagebar->PageBarCount = $zbp->pagebarcount;
    //$pagebar->Count = $post->CommNums;

    $comments = array();

    $comments = $zbp->GetCommentList(
        '*',
        array(
            array('=', 'comm_LogID', $post->ID),
            array('=', 'comm_RootID', 0),
            array('=', 'comm_IsChecking', 0),
        ),
        array('comm_ID' => ($zbp->option['ZC_COMMENT_REVERSE_ORDER'] ? 'DESC' : 'ASC')),
        array(($pagebar->PageNow - 1) * $pagebar->PageCount, $pagebar->PageCount),
        array('pagebar' => $pagebar)
    );
    $rootid = array();
    foreach ($comments as $comment) {
        $rootid[] = array('comm_RootID', $comment->ID);
    }
    $comments2 = $zbp->GetCommentList(
        '*',
        array(
            array('=', 'comm_LogID', $post->ID),
            array('array', $rootid),
            array('=', 'comm_IsChecking', 0),
        ),
        array('comm_ID' => ($zbp->option['ZC_COMMENT_REVERSE_ORDER'] ? 'DESC' : 'ASC')),
        null,
        null
    );

    $floorid = (($pagebar->PageNow - 1) * $pagebar->PageCount);
    foreach ($comments as &$comment) {
        $floorid += 1;
        $comment->FloorID = $floorid;
        $comment->Content = FormatString($comment->Content, '[enter]');
        if (strpos($zbp->template->templates['comment'], 'id="AjaxComment') === false) {
            $comment->Content .= '<label id="AjaxComment' . $comment->ID . '"></label>';
        }
    }
    foreach ($comments2 as &$comment) {
        $comment->Content = FormatString($comment->Content, '[enter]');
        if (strpos($zbp->template->templates['comment'], 'id="AjaxComment') === false) {
            $comment->Content .= '<label id="AjaxComment' . $comment->ID . '"></label>';
        }
    }

    $zbp->template->SetTags('title', $zbp->title);
    $zbp->template->SetTags('article', $post);
    $zbp->template->SetTags('type', 'comment');
    $zbp->template->SetTags('page', $page);
    if ($pagebar->PageAll == 1) {
        $pagebar = null;
    }

    $zbp->template->SetTags('pagebar', $pagebar);
    $zbp->template->SetTags('comments', $comments);

    $zbp->template->SetTemplate($template);

    foreach ($GLOBALS['hooks']['Filter_Plugin_ViewComments_Template'] as $fpname => &$fpsignal) {
        $fpreturn = $fpname($zbp->template);
    }

    $s = $zbp->template->Output();

    $a = explode('<label id="AjaxCommentBegin"></label>', $s);
    $s = $a[1];
    $a = explode('<label id="AjaxCommentEnd"></label>', $s);
    $s = $a[0];

    echo $s;

    return true;
}

/**
 * 显示评论.
 *
 * @param int $id 评论ID
 *
 * @throws Exception
 *
 * @return bool
 */
function ViewComment($id)
{
    global $zbp;

    $template = 'comment';
    /* @var Comment $comment */
    $comment = $zbp->GetCommentByID($id);
    $post = new Post();
    $post = $zbp->GetPostByID($comment->LogID);

    $comment->Content = FormatString(htmlspecialchars($comment->Content), '[enter]');
    if (strpos($zbp->template->templates['comment'], 'id="AjaxComment') === false) {
        $comment->Content .= '<label id="AjaxComment' . $comment->ID . '"></label>';
    }

    $zbp->template->SetTags('title', $zbp->title);
    $zbp->template->SetTags('comment', $comment);
    $zbp->template->SetTags('article', $post);
    $zbp->template->SetTags('type', 'comment');
    $zbp->template->SetTags('page', 1);
    $zbp->template->SetTemplate($template);

    foreach ($GLOBALS['hooks']['Filter_Plugin_ViewComment_Template'] as $fpname => &$fpsignal) {
        $fpreturn = $fpname($zbp->template);
    }

    $zbp->template->Display();

    return true;
}

//###############################################################################################################

/**
 * 提交文章数据.
 *
 * @api Filter_Plugin_PostArticle_Core
 * @api Filter_Plugin_PostArticle_Succeed
 *
 * @throws Exception
 *
 * @return bool
 */
function PostArticle()
{
    global $zbp;
    if (!isset($_POST['ID'])) {
        return false;
    }

    if (isset($_COOKIE['timezone'])) {
        $tz = GetVars('timezone', 'COOKIE');
        if (is_numeric($tz)) {
            date_default_timezone_set('Etc/GMT' . sprintf('%+d', -$tz));
        }
        unset($tz);
    }

    if (isset($_POST['Tag'])) {
        $_POST['Tag'] = FormatString($_POST['Tag'], '[noscript]');
        $_POST['Tag'] = PostArticle_CheckTagAndConvertIDtoString($_POST['Tag']);
    }
    if (isset($_POST['Content'])) {
        $_POST['Content'] = preg_replace("/<hr class=\"more\"\s*\/>/i", '<!--more-->', $_POST['Content']);
        $intro = isset($_POST['Intro']) ? $_POST['Intro'] : '';
        //获取内容的第一张图片
        preg_match_all('/<img[^>]*?\s+src="([^\s"]{5,})"[^>]*?>/i', $intro . $_POST['Content'], $imgs);
        $img = isset($imgs[1][0]) ? $imgs[1][0] : false;
        //原图
        if($img) {
            $_POST['FirstImg'] = str_replace($zbp->host, '', $img);
            $_POST['FirstImg'] = substr($_POST['FirstImg'], 0,1)=='/' || substr($_POST['FirstImg'], 0,1)=='\\' ? substr($_POST['FirstImg'], 1):$_POST['FirstImg'];
            $_POST['FirstImg'] = '{#ZC_BLOG_HOST#}'.$_POST['FirstImg'];
        }
        //缩略图处理
        if($img && $zbp->option['ZC_ARTICLE_THUMB_SWITCH']){
            //先通过$zbp->host判断是否为本地图片
            if(stripos($img,$zbp->host) !== false) {
                //将图片地址的host干掉
                $img = str_replace($zbp->host, '', $img);
                //绝对地址
                $imgs = $zbp->path.str_replace($zbp->host, '', $img);
                if(is_file($imgs)){
                    //缩略图路径
                    $imgNew = ImgToThumbUrl($imgs);
                    //缩略并裁剪
                    if($zbp->option['ZC_ARTICLE_THUMB_TYPE'] == 1){
                        if(ZbpImage::ClipThumb($imgs,$imgNew,$zbp->option['ZC_ARTICLE_THUMB_WIDTH'],$zbp->option['ZC_ARTICLE_THUMB_HEIGHT'])){
                            $_POST['Thumb'] = ImgToThumbUrl('{#ZC_BLOG_HOST#}'.$img);
                        }
                    }else{
                        if(ZbpImage::Thumb($imgs,$imgNew,$zbp->option['ZC_ARTICLE_THUMB_WIDTH'],false)){
                            $_POST['Thumb'] = ImgToThumbUrl('{#ZC_BLOG_HOST#}'.$img);
                        }
                    }
                    
                }
            }else{
                $path = 'zb_users/upload/' . date('Y/m') . '/';
                $dir = ZBP_PATH.$path;
                //如果设置的上传目录不存在，则创建
                if (!file_exists($dir)) @mkdir($dir,0777,true);
                $ext = strtolower(substr(strrchr($img, '.'), 1));
                $name = date('Ymd').(microtime(true)*10000).'.'.$ext;
                $url = $dir.$name;
                //网络地址
                if(preg_match('/^(http|https):\/\//',$img)){
                    $http = Network::Create();
                    $http->open('GET',$img);
                    $http->setRequestHeader('Referer', $zbp->host);
                    $http->send();
                    if ($http->status == 200){
                        $r = $http->responseText;
                        if($r){
                            if(file_put_contents($url,$r)){
                                $imgNew = ImgToThumbUrl($url);
                                if(ZbpImage::ClipThumb($url,$imgNew,$zbp->option['ZC_ARTICLE_THUMB_WIDTH'],$zbp->option['ZC_ARTICLE_THUMB_HEIGHT'])){
                                    $_POST['Thumb'] = '{#ZC_BLOG_HOST#}'.$path.ImgToThumbUrl($name);
                                }
                            }
                        }
                    }
                }else{
                    $r = $zbp->path.str_replace('{#ZC_BLOG_HOST#}', '', $_POST['FirstImg']);
                    $imgNew = ImgToThumbUrl($url);
                    if(ZbpImage::ClipThumb($r,$imgNew,$zbp->option['ZC_ARTICLE_THUMB_WIDTH'],$zbp->option['ZC_ARTICLE_THUMB_HEIGHT'])){
                        $_POST['Thumb'] = '{#ZC_BLOG_HOST#}'.$path.ImgToThumbUrl($name);
                    }

                }
            }
        }

        if (isset($_POST['Intro'])) {
            if (stripos($_POST['Content'], '<!--more-->') !== false) {
                $_POST['Intro'] = GetValueInArray(explode('<!--more-->', $_POST['Content']), 0);
            }
            if (trim($_POST['Intro']) == '' || (stripos($_POST['Intro'], '<!--autointro-->') !== false)) {
                if ($zbp->option['ZC_ARTICLE_INTRO_WITH_TEXT'] == true) {
                    //改纯HTML摘要
                    $i = (int) $zbp->option['ZC_ARTICLE_EXCERPT_MAX'];
                    $_POST['Intro'] = FormatString($_POST['Content'], "[nohtml]");
                    $_POST['Intro'] = SubStrUTF8_Html($_POST['Intro'], $i);
                } else {
                    $i = (int) $zbp->option['ZC_ARTICLE_EXCERPT_MAX'];
                    if (Zbp_StrLen($_POST['Content']) > $i) {
                        $i = (int) Zbp_Strpos($_POST['Content'], '>', $i);
                    }
                    if ($i == 0) {
                        $i = (int) Zbp_StrLen($_POST['Content']);
                    }
                    if ($i < $zbp->option['ZC_ARTICLE_EXCERPT_MAX']) {
                        $i = (int) $zbp->option['ZC_ARTICLE_EXCERPT_MAX'];
                    }
                    $_POST['Intro'] = SubStrUTF8_Html($_POST['Content'], $i);
                    $_POST['Intro'] = CloseTags($_POST['Intro']);
                }

                $_POST['Intro'] .= '<!--autointro-->';
            } else {
                if ($zbp->option['ZC_ARTICLE_INTRO_WITH_TEXT'] == true) {
                    //改纯HTML摘要
                    $_POST['Intro'] = FormatString($_POST['Intro'], "[nohtml]");
                }
                $_POST['Intro'] = CloseTags($_POST['Intro']);
            }
        }
    }

    if (!isset($_POST['AuthorID'])) {
        $_POST['AuthorID'] = $zbp->user->ID;
    } else {
        if (($_POST['AuthorID'] != $zbp->user->ID) && (!$zbp->CheckRights('ArticleAll'))) {
            $_POST['AuthorID'] = $zbp->user->ID;
        }
        if (empty($_POST['AuthorID'])) {
            $_POST['AuthorID'] = $zbp->user->ID;
        }
    }

    if (isset($_POST['Alias'])) {
        $_POST['Alias'] = FormatString($_POST['Alias'], '[noscript]');
    }

    if (isset($_POST['PostTime'])) {
        $_POST['PostTime'] = strtotime($_POST['PostTime']);
    }

    if (!$zbp->CheckRights('ArticleAll')) {
        unset($_POST['IsTop']);
    }

    $article = new Post();
    $pre_author = null;
    $pre_tag = null;
    $pre_category = null;
    $pre_istop = null;
    $pre_status = null;
    $orig_id = 0;

    if (GetVars('ID', 'POST') == 0) {
        if (!$zbp->CheckRights('ArticlePub')) {
            $_POST['Status'] = ZC_POST_STATUS_AUDITING;
        }
    } else {
        $article = $zbp->GetPostByID(GetVars('ID', 'POST'));
        if (($article->AuthorID != $zbp->user->ID) && (!$zbp->CheckRights('ArticleAll'))) {
            $zbp->ShowError(6, __FILE__, __LINE__);
        }
        if ((!$zbp->CheckRights('ArticlePub')) && ($article->Status == ZC_POST_STATUS_AUDITING)) {
            $_POST['Status'] = ZC_POST_STATUS_AUDITING;
        }
        $orig_id = $article->ID;
        $pre_author = $article->AuthorID;
        $pre_tag = $article->Tag;
        $pre_category = $article->CateID;
        $pre_istop = $article->IsTop;
        $pre_status = $article->Status;
    }

    foreach ($zbp->datainfo['Post'] as $key => $value) {
        if ($key == 'ID' || $key == 'Meta') {
            continue;
        }
        if (isset($_POST[$key])) {
            $article->$key = GetVars($key, 'POST');
        }
    }

    $article->Type = ZC_POST_TYPE_ARTICLE;

    $article->UpdateTime = time();

    FilterMeta($article);

    foreach ($GLOBALS['hooks']['Filter_Plugin_PostArticle_Core'] as $fpname => &$fpsignal) {
        $fpname($article);
    }

    FilterPost($article);

    $article->Save();
    $zbp->AddCache($article);

    //更新统计信息
    $pre_arrayTag = $zbp->LoadTagsByIDString($pre_tag);
    $now_arrayTag = $zbp->LoadTagsByIDString($article->Tag);
    $pre_array = $now_array = array();
    foreach ($pre_arrayTag as $tag) {
        $pre_array[] = $tag->ID;
    }
    foreach ($now_arrayTag as $tag) {
        $now_array[] = $tag->ID;
    }
    $del_array = array_diff($pre_array, $now_array);
    $add_array = array_diff($now_array, $pre_array);
    $del_string = $zbp->ConvertTagIDtoString($del_array);
    $add_string = $zbp->ConvertTagIDtoString($add_array);
    if ($del_string) {
        CountTagArrayString($del_string, -1, $article->ID);
    }
    if ($add_string) {
        CountTagArrayString($add_string, +1, $article->ID);
    }
    if ($pre_author != $article->AuthorID) {
        if ($pre_author > 0) {
            CountMemberArray(array($pre_author), array(-1, 0, 0, 0));
        }

        CountMemberArray(array($article->AuthorID), array(+1, 0, 0, 0));
    }
    if ($pre_category != $article->CateID) {
        if ($pre_category > 0) {
            CountCategoryArray(array($pre_category), -1);
        }

        CountCategoryArray(array($article->CateID), +1);
    }
    if ($zbp->option['ZC_LARGE_DATA'] == false) {
        CountPostArray(array($article->ID));
    }
    if ($orig_id == 0 && $article->IsTop == 0 && $article->Status == ZC_POST_STATUS_PUBLIC) {
        CountNormalArticleNums(+1);
    } elseif ($orig_id > 0) {
        if (($pre_istop == 0 && $pre_status == 0) && ($article->IsTop != 0 || $article->Status != 0)) {
            CountNormalArticleNums(-1);
        }
        if (($pre_istop != 0 || $pre_status != 0) && ($article->IsTop == 0 && $article->Status == 0)) {
            CountNormalArticleNums(+1);
        }
    }
    if ($article->IsTop == true && $article->Status == ZC_POST_STATUS_PUBLIC) {
        CountTopPost($article->Type, $article->ID, null);
    } else {
        CountTopPost($article->Type, null, $article->ID);
    }

    $zbp->AddBuildModule('previous');
    $zbp->AddBuildModule('calendar');
    $zbp->AddBuildModule('comments');
    $zbp->AddBuildModule('archives');
    $zbp->AddBuildModule('tags');
    $zbp->AddBuildModule('authors');

    foreach ($GLOBALS['hooks']['Filter_Plugin_PostArticle_Succeed'] as $fpname => &$fpsignal) {
        $fpname($article);
    }

    return $article;
}

/**
 * 删除文章.
 *
 * @throws Exception
 *
 * @return bool
 */
function DelArticle()
{
    global $zbp;

    $id = (int) GetVars('id');

    $article = new Post();
    $article = $zbp->GetPostByID($id);
    if ($article->ID > 0) {
        if (!$zbp->CheckRights('ArticleAll') && $article->AuthorID != $zbp->user->ID) {
            $zbp->ShowError(6, __FILE__, __LINE__);
        }

        $pre_author = $article->AuthorID;
        $pre_tag = $article->Tag;
        $pre_category = $article->CateID;
        $pre_istop = $article->IsTop;
        $pre_status = $article->Status;

        $article->Del();

        DelArticle_Comments($article->ID);

        CountTagArrayString($pre_tag, -1, $article->ID);
        CountMemberArray(array($pre_author), array(-1, 0, 0, 0));
        CountCategoryArray(array($pre_category), -1);
        if (($pre_istop == 0 && $pre_status == 0)) {
            CountNormalArticleNums(-1);
        }
        if ($article->IsTop == true) {
            CountTopPost($article->Type, null, $article->ID);
        }

        $zbp->AddBuildModule('previous');
        $zbp->AddBuildModule('calendar');
        $zbp->AddBuildModule('comments');
        $zbp->AddBuildModule('archives');
        $zbp->AddBuildModule('tags');
        $zbp->AddBuildModule('authors');

        foreach ($GLOBALS['hooks']['Filter_Plugin_DelArticle_Succeed'] as $fpname => &$fpsignal) {
            $fpname($article);
        }

        return true;
    }

    return false;
}

/**
 * 提交文章数据时检查tag数据，并将新tags转为标准格式返回.
 *
 * @param string $tagnamestring 提交的文章tag数据，可以:,，、等符号分隔
 *
 * @return string 返回如'{1}{2}{3}{4}'的字符串
 */
function PostArticle_CheckTagAndConvertIDtoString($tagnamestring, $post_type = 0)
{
    global $zbp;
    $s = '';
    $tagnamestring = str_replace(array(';', '，', '、'), ',', $tagnamestring);
    $tagnamestring = strip_tags($tagnamestring);
    $tagnamestring = trim($tagnamestring);
    if ($tagnamestring == '') {
        return '';
    }

    if ($tagnamestring == ',') {
        return '';
    }

    $a = explode(',', $tagnamestring);
    $b = array();
    foreach ($a as $value) {
        $v = trim($value);
        if ($v) {
            $b[] = $v;
        }
    }
    $b = array_unique($b);
    $b = array_slice($b, 0, 20);
    $c = array();

    $t = $zbp->LoadTagsByNameString($tagnamestring, $post_type);
    foreach ($t as $key => $value) {
        $c[] = $key;
    }

    $d = array_diff($b, $c);
    if ($zbp->CheckRights('TagNew')) {
        foreach ($d as $key) {
            $tag = new Tag();
            $tag->Name = $key;

            foreach ($GLOBALS['hooks']['Filter_Plugin_PostTag_Core'] as $fpname => &$fpsignal) {
                $fpname($tag);
            }

            FilterTag($tag);
            $tag->Save();
            $zbp->tags[$tag->ID] = $tag;
            $zbp->tagsbyname[$tag->Name] = &$zbp->tags[$tag->ID];

            foreach ($GLOBALS['hooks']['Filter_Plugin_PostTag_Succeed'] as $fpname => &$fpsignal) {
                $fpname($tag);
            }
        }
    }

    foreach ($b as $key) {
        if (!isset($zbp->tagsbyname_type[$post_type][$key])) {
            continue;
        }

        $s .= '{' . $zbp->tagsbyname_type[$post_type][$key]->ID . '}';
    }

    return $s;
}

/**
 * 删除文章下所有评论.
 *
 * @param int $id 文章ID
 */
function DelArticle_Comments($id)
{
    global $zbp;

    $sql = $zbp->db->sql->Delete($zbp->table['Comment'], array(array('=', 'comm_LogID', $id)));
    $zbp->db->Delete($sql);
}

//###############################################################################################################

/**
 * 提交页面数据.
 *
 * @throws Exception
 *
 * @return bool
 */
function PostPage()
{
    global $zbp;
    if (!isset($_POST['ID'])) {
        return false;
    }

    if (isset($_POST['PostTime'])) {
        $_POST['PostTime'] = strtotime($_POST['PostTime']);
    }

    if (!isset($_POST['AuthorID'])) {
        $_POST['AuthorID'] = $zbp->user->ID;
    } else {
        if (($_POST['AuthorID'] != $zbp->user->ID) && (!$zbp->CheckRights('PageAll'))) {
            $_POST['AuthorID'] = $zbp->user->ID;
        }
    }

    if (isset($_POST['Alias'])) {
        $_POST['Alias'] = FormatString($_POST['Alias'], '[noscript]');
    }

    $article = new Post();
    $pre_author = null;
    $orig_id = 0;
    if (GetVars('ID', 'POST') == 0) {
        $i = 0;
    } else {
        $article = $zbp->GetPostByID(GetVars('ID', 'POST'));
        if (($article->AuthorID != $zbp->user->ID) && (!$zbp->CheckRights('PageAll'))) {
            $zbp->ShowError(6, __FILE__, __LINE__);
        }
        $pre_author = $article->AuthorID;
        $orig_id = $article->ID;
    }

    foreach ($zbp->datainfo['Post'] as $key => $value) {
        if ($key == 'ID' || $key == 'Meta') {
            continue;
        }
        if (isset($_POST[$key])) {
            $article->$key = GetVars($key, 'POST');
        }
    }

    $article->Type = ZC_POST_TYPE_PAGE;

    FilterMeta($article);

    foreach ($GLOBALS['hooks']['Filter_Plugin_PostPage_Core'] as $fpname => &$fpsignal) {
        $fpname($article);
    }

    FilterPost($article);

    $article->Save();

    if ($pre_author != $article->AuthorID) {
        if ($pre_author > 0) {
            CountMemberArray(array($pre_author), array(0, -1, 0, 0));
        }

        CountMemberArray(array($article->AuthorID), array(0, +1, 0, 0));
    }
    if ($zbp->option['ZC_LARGE_DATA'] == false) {
        CountPostArray(array($article->ID));
    }

    $zbp->AddBuildModule('comments');

    if (GetVars('AddNavbar', 'POST') == 0) {
        $zbp->DelItemToNavbar('page', $article->ID);
    }

    if (GetVars('AddNavbar', 'POST') == 1) {
        $zbp->AddItemToNavbar('page', $article->ID, $article->Title, $article->Url);
    }

    foreach ($GLOBALS['hooks']['Filter_Plugin_PostPage_Succeed'] as $fpname => &$fpsignal) {
        $fpname($article);
    }

    return $article;
}

/**
 * 删除页面.
 *
 * @throws Exception
 *
 * @return bool
 */
function DelPage()
{
    global $zbp;

    $id = (int) GetVars('id');

    $article = new Post();
    $article = $zbp->GetPostByID($id);
    if ($article->ID > 0) {
        if (!$zbp->CheckRights('PageAll') && $article->AuthorID != $zbp->user->ID) {
            $zbp->ShowError(6, __FILE__, __LINE__);
        }

        $pre_author = $article->AuthorID;

        $article->Del();

        DelArticle_Comments($article->ID);

        CountMemberArray(array($pre_author), array(0, -1, 0, 0));

        $zbp->AddBuildModule('comments');

        $zbp->DelItemToNavbar('page', $article->ID);

        foreach ($GLOBALS['hooks']['Filter_Plugin_DelPage_Succeed'] as $fpname => &$fpsignal) {
            $fpname($article);
        }
    }

    return true;
}

/**
 * 批量删除Post.
 *
 * @param $type
 */
function BatchPost($type)
{
    foreach ($GLOBALS['hooks']['Filter_Plugin_BatchPost'] as $fpname => &$fpsignal) {
        $fpreturn = $fpname($type);
    }
}

//###############################################################################################################

/**
 * 提交评论.
 *
 * @throws Exception
 *
 * @return bool
 */
function PostComment()
{
    global $zbp;

    $isAjax = GetVars('isajax', 'POST');
    $returnJson = GetVars('format', 'POST') == 'json';
    $returnCommentWhiteList = array(
        'ID'       => null,
        'Content'  => null,
        'LogId'    => null,
        'Name'     => null,
        'ParentID' => null,
        'PostTime' => null,
        'HomePage' => null,
        'Email'    => null,
        'AuthorID' => null,
    );

    $_POST['LogID'] = $_GET['postid'];

    if ($zbp->ValidCmtKey($_GET['postid'], $_GET['key']) == false) {
        if (isset($zbp->option['ZC_COMMENT_VALIDCMTKEY_ENABLE']) && $zbp->option['ZC_COMMENT_VALIDCMTKEY_ENABLE']) {
            $zbp->ShowError(43, __FILE__, __LINE__);
        }
    }

    if ($zbp->option['ZC_COMMENT_VERIFY_ENABLE']) {
        if (!$zbp->CheckRights('NoValidCode')) {
            if ($zbp->CheckValidCode($_POST['verify'], 'cmt') == false) {
                $zbp->ShowError(38, __FILE__, __LINE__);
            }
        }
    }

    $post_name = isset($_POST['name']) ? GetVars('name', 'POST') : GetVars('Name', 'POST');
    $post_replyid = isset($_POST['replyid']) ? GetVars('replyid', 'POST') : GetVars('ReplyID', 'POST');
    $post_email = isset($_POST['email']) ? GetVars('email', 'POST') : GetVars('Email', 'POST');
    $post_homepage = isset($_POST['homepage']) ? GetVars('homepage', 'POST') : GetVars('HomePage', 'POST');
    $post_content = isset($_POST['content']) ? GetVars('content', 'POST') : GetVars('Content', 'POST');

    //判断是不是有同名的用户
    $m = $zbp->GetMemberByName($post_name);
    if ($m->ID > 0) {
        if ($m->ID != $zbp->user->ID) {
            $zbp->ShowError(31, __FILE__, __LINE__);
        }
    }

    $replyid = (int) $post_replyid;

    if ($replyid == 0) {
        $_POST['RootID'] = 0;
        $_POST['ParentID'] = 0;
    } else {
        $_POST['ParentID'] = $replyid;
        $c = $zbp->GetCommentByID($replyid);
        if ($c->Level > ($zbp->comment_recursion_level - 2)) {
            $zbp->ShowError(52, __FILE__, __LINE__);
        }
        $_POST['RootID'] = Comment::GetRootID($c->ID);
    }

    $_POST['AuthorID'] = $zbp->user->ID;
    $_POST['Name'] = $post_name;
    $_POST['Email'] = $post_email;
    $_POST['HomePage'] = $post_homepage;
    $_POST['Content'] = $post_content;
    $_POST['PostTime'] = time();
    $_POST['IP'] = GetGuestIP();
    $_POST['Agent'] = GetGuestAgent();

    if ($zbp->user->ID > 0) {
        $_POST['Name'] = $zbp->user->Name;
    }

    $cmt = new Comment();

    foreach ($zbp->datainfo['Comment'] as $key => $value) {
        if ($key == 'ID' || $key == 'Meta') {
            continue;
        }
        if ($key == 'IsChecking') {
            continue;
        }

        if (isset($_POST[$key])) {
            $cmt->$key = GetVars($key, 'POST');
        }
    }

    if ($zbp->option['ZC_COMMENT_AUDIT'] && !$zbp->CheckRights('root')) {
        $cmt->IsChecking = true;
    }

    foreach ($GLOBALS['hooks']['Filter_Plugin_PostComment_Core'] as $fpname => &$fpsignal) {
        $fpname($cmt);
    }

    FilterComment($cmt);

    if ($cmt->IsThrow) {
        $zbp->ShowError(14, __FILE__, __LINE__);

        return false;
    }

    $cmt->Save();
    $zbp->AddCache($cmt);

    if ($cmt->IsChecking) {
        CountCommentNums(0, +1);
        $zbp->ShowError(53, __FILE__, __LINE__);

        return false;
    }

    CountPostArray(array($cmt->LogID), +1);
    CountCommentNums(+1, 0);
    if ($zbp->user->ID > 0) {
        CountMember($zbp->user, array(0, 0, 1, 0));
        $zbp->user->Save();
    }

    $zbp->AddBuildModule('comments');

    if ($isAjax) {
        ViewComment($cmt->ID);
    } elseif ($returnJson) {
        ob_clean();
        ViewComment($cmt->ID);
        $commentHtml = ob_get_clean();
        JsonReturn(
            array_merge_recursive(
                array(
                    "html" => $commentHtml,
                ),
                array_intersect_key($cmt->GetData(), $returnCommentWhiteList)
            )
        );
    }

    foreach ($GLOBALS['hooks']['Filter_Plugin_PostComment_Succeed'] as $fpname => &$fpsignal) {
        $fpname($cmt);
    }

    return $cmt;
}

/**
 * 删除评论.
 *
 * @return bool
 */
function DelComment()
{
    global $zbp;

    $id = (int) GetVars('id', 'GET');
    $cmt = $zbp->GetCommentByID($id);
    if ($cmt->ID > 0) {
        $comments = $zbp->GetCommentList('*', array(array('=', 'comm_LogID', $cmt->LogID)), null, null, null);

        DelComment_Children($cmt->ID);

        if ($cmt->IsChecking == false) {
            CountCommentNums(-1, 0);
        } else {
            CountCommentNums(-1, -1);
        }
        $cmt->Del();

        if ($cmt->IsChecking == false) {
            CountPostArray(array($cmt->LogID), -1);
            if ($cmt->AuthorID > 0) {
                CountMember($cmt->Author, array(0, 0, -1, 0));
                $cmt->Author->Save();
            }
        }

        $zbp->AddBuildModule('comments');

        foreach ($GLOBALS['hooks']['Filter_Plugin_DelComment_Succeed'] as $fpname => &$fpsignal) {
            $fpname($cmt);
        }
    }

    return true;
}

/**
 * 删除评论下的子评论.
 *
 * @param int $id 父评论ID
 */
function DelComment_Children($id)
{
    global $zbp;

    $cmt = $zbp->GetCommentByID($id);

    foreach ($cmt->Comments as $comment) {
        if (count($comment->Comments) > 0) {
            DelComment_Children($comment->ID);
        }
        if ($comment->IsChecking == false) {
            CountCommentNums(-1, 0);
        } else {
            CountCommentNums(-1, -1);
        }
        $comment->Del();
    }
}

/**
 * 只历遍并保留评论id进array,不进行删除.
 *
 * @param int       $id    父评论ID
 * @param Comment[] $array 将子评论ID存入新数组
 */
function GetSubComments($id, &$array)
{
    global $zbp;

    /* @var Comment $cmt */
    $cmt = $zbp->GetCommentByID($id);

    foreach ($cmt->Comments as $comment) {
        $array[] = $comment->ID;
        if (count($comment->Comments) > 0) {
            GetSubComments($comment->ID, $array);
        }
    }
}

/**
 *检查评论数据并保存、更新计数、更新“最新评论”模块.
 */
function CheckComment()
{
    global $zbp;

    $id = (int) GetVars('id');
    $ischecking = (bool) GetVars('ischecking');

    $cmt = $zbp->GetCommentByID($id);
    $orig_check = (bool) $cmt->IsChecking;
    $cmt->IsChecking = $ischecking;

    foreach ($GLOBALS['hooks']['Filter_Plugin_CheckComment_Core'] as $fpname => &$fpsignal) {
        $fpname($cmt);
    }

    $cmt->Save();

    foreach ($GLOBALS['hooks']['Filter_Plugin_CheckComment_Succeed'] as $fpname => &$fpsignal) {
        $fpname($cmt);
    }

    if (($orig_check) && (!$ischecking)) {
        CountPostArray(array($cmt->LogID), +1);
        CountCommentNums(0, -1);
        if ($cmt->AuthorID > 0) {
            CountMember($cmt->Author, array(0, 0, +1, 0));
            $cmt->Author->Save();
        }
    } elseif ((!$orig_check) && ($ischecking)) {
        CountPostArray(array($cmt->LogID), -1);
        CountCommentNums(0, +1);
        if ($cmt->AuthorID > 0) {
            CountMember($cmt->Author, array(0, 0, -1, 0));
            $cmt->Author->Save();
        }
    }

    $zbp->AddBuildModule('comments');
}

/**
 * 评论批量处理（删除、通过审核、加入审核）.
 */
function BatchComment()
{
    global $zbp;
    if (isset($_POST['all_del'])) {
        $type = 'all_del';
    } elseif (isset($_POST['all_pass'])) {
        $type = 'all_pass';
    } elseif (isset($_POST['all_audit'])) {
        $type = 'all_audit';
    } else {
        return;
    }
    if (!isset($_POST['id'])) {
        return;
    }
    $array = $_POST['id'];
    if (is_array($array)) {
        $array = array_unique($array);
    } else {
        $array = array($array);
    }

    $childArray = $zbp->GetCommentByArray($array);

    // Search Child Comments
    /* @var Comment[] $childArray */
    //$childArray = array();
    //foreach ($array as $i => $id) {
    //    $cmt = $zbp->GetCommentByID($id);
    //    if ($cmt->ID == 0) {
    //        continue;
    //    }
    //    $childArray[] = $cmt;
    //    GetSubComments($cmt->ID, $childArray);
    //}

    // Unique child array
    //$childArray = array_unique($childArray);
    //foreach ($childArray as $key => $value) {
    //    if (is_int($value)) {
    //        $childArray[$key] = $zbp->GetCommentByID($value);
    //    }
    //    if (is_subclass_of($childArray[$key], 'Base') == false || $childArray[$key]->ID == 0) {
    //        unset($childArray[$key]);
    //    }
    //}

    if ($type == 'all_del') {
        foreach ($childArray as $i => $cmt) {
            $cmt->Del();
            if (!$cmt->IsChecking) {
                CountPostArray(array($cmt->LogID), -1);
                CountCommentNums(-1, 0);
                if ($cmt->AuthorID > 0) {
                    CountMember($cmt->Author, array(0, 0, -1, 0));
                    $cmt->Author->Save();
                }
            } else {
                CountCommentNums(-1, -1);
            }
        }
    } elseif ($type == 'all_pass') {
        foreach ($childArray as $i => $cmt) {
            if (!$cmt->IsChecking) {
                continue;
            }

            $cmt->IsChecking = false;
            $cmt->Save();
            CountPostArray(array($cmt->LogID), +1);
            CountCommentNums(0, -1);
            if ($cmt->AuthorID > 0) {
                CountMember($cmt->Author, array(0, 0, 1, 0));
                $cmt->Author->Save();
            }
        }
    } elseif ($type == 'all_audit') {
        foreach ($childArray as $i => $cmt) {
            if ($cmt->IsChecking) {
                continue;
            }

            $cmt->IsChecking = true;
            $cmt->Save();
            CountPostArray(array($cmt->LogID), -1);
            CountCommentNums(0, +1);
            if ($cmt->AuthorID > 0) {
                CountMember($cmt->Author, array(0, 0, -1, 0));
                $cmt->Author->Save();
            }
        }
    }

    $zbp->AddBuildModule('comments');
}

//###############################################################################################################

/**
 * 提交分类数据.
 *
 * @return bool
 */
function PostCategory()
{
    global $zbp;
    if (!isset($_POST['ID'])) {
        return false;
    }

    if (isset($_POST['Alias'])) {
        $_POST['Alias'] = FormatString($_POST['Alias'], '[noscript]');
    }

    $parentid = (int) GetVars('ParentID', 'POST');
    if ($parentid > 0) {
        if (isset($zbp->categories_all[$parentid]) && $zbp->categories_all[$parentid]->Level > ($zbp->category_recursion_level - 2)) {
            $_POST['ParentID'] = '0';
        }
    }

    $cate = new Category();
    if (GetVars('ID', 'POST') == 0) {
        $i = 0;
    } else {
        $cate = $zbp->GetCategoryByID(GetVars('ID', 'POST'));
    }

    foreach ($zbp->datainfo['Category'] as $key => $value) {
        if ($key == 'ID' || $key == 'Meta') {
            continue;
        }
        if (isset($_POST[$key])) {
            $cate->$key = GetVars($key, 'POST');
        }
    }

    FilterMeta($cate);

    //刷新RootID
    $cate->Level;

    foreach ($GLOBALS['hooks']['Filter_Plugin_PostCategory_Core'] as $fpname => &$fpsignal) {
        $fpname($cate);
    }

    FilterCategory($cate);

    // 此处用作刷新分类内文章数据使用，不作更改
    if ($cate->ID > 0) {
        CountCategory($cate, null, $cate->Type);
    }

    $cate->Save();

    $zbp->LoadCategories();
    $zbp->AddBuildModule('catalog');

    if (GetVars('AddNavbar', 'POST') == 0) {
        $zbp->DelItemToNavbar('category', $cate->ID);
    }

    if (GetVars('AddNavbar', 'POST') == 1) {
        $zbp->AddItemToNavbar('category', $cate->ID, $cate->Name, $cate->Url);
    }

    foreach ($GLOBALS['hooks']['Filter_Plugin_PostCategory_Succeed'] as $fpname => &$fpsignal) {
        $fpname($cate);
    }

    return $cate;
}

/**
 * 删除分类.
 *
 * @throws Exception
 *
 * @return bool
 */
function DelCategory()
{
    global $zbp;

    $id = (int) GetVars('id');
    $cate = $zbp->GetCategoryByID($id);
    if ($cate->ID > 0) {
        if (count($cate->SubCategories) > 0) {
            $zbp->ShowError(49, __FILE__, __LINE__);

            return false;
        }

        DelCategory_Articles($cate->ID);
        $cate->Del();

        $zbp->LoadCategories();
        $zbp->AddBuildModule('catalog');
        $zbp->DelItemToNavbar('category', $cate->ID);

        foreach ($GLOBALS['hooks']['Filter_Plugin_DelCategory_Succeed'] as $fpname => &$fpsignal) {
            $fpname($cate);
        }

        return true;
    }

    return false;
}

/**
 * 删除分类下所有文章.
 *
 * @param int $id 分类ID
 */
function DelCategory_Articles($id)
{
    global $zbp;

    $sql = $zbp->db->sql->Update($zbp->table['Post'], array('log_CateID' => 0), array(array('=', 'log_CateID', $id)));
    $zbp->db->Update($sql);
}

//###############################################################################################################

/**
 * 提交标签数据.
 *
 * @return bool
 */
function PostTag()
{
    global $zbp;
    if (!isset($_POST['ID'])) {
        return false;
    }

    if (isset($_POST['Alias'])) {
        $_POST['Alias'] = FormatString($_POST['Alias'], '[noscript]');
    }

    $tag = new Tag();
    if (GetVars('ID', 'POST') == 0) {
        $i = 0;
    } else {
        $tag = $zbp->GetTagByID(GetVars('ID', 'POST'));
    }

    foreach ($zbp->datainfo['Tag'] as $key => $value) {
        if ($key == 'ID' || $key == 'Meta') {
            continue;
        }
        if (isset($_POST[$key])) {
            $tag->$key = GetVars($key, 'POST');
        }
    }

    FilterMeta($tag);

    foreach ($GLOBALS['hooks']['Filter_Plugin_PostTag_Core'] as $fpname => &$fpsignal) {
        $fpname($tag);
    }

    FilterTag($tag);

    if ($zbp->option['ZC_LARGE_DATA'] == false) {
        CountTag($tag);
    }

    //检查Name重名(用GetTagList不用GetTagByName)
    $array = $zbp->GetTagList('*', array(array('=', 'tag_Name', $tag->Name), array('=', 'tag_Type', $tag->Type)), '', 1, '');
    $checkTag = new Tag();
    if (count($array) > 0) {
        $checkTag = $array[0];
    }
    if (($tag->ID == 0 && $checkTag->ID > 0) || ($tag->ID > 0 && $checkTag->ID > 0 && $checkTag->ID != $tag->ID)){
        $zbp->ShowError(98, __FILE__, __LINE__);
    }

    $tag->Save();
    $zbp->AddCache($tag);

    if (GetVars('AddNavbar', 'POST') == 0) {
        $zbp->DelItemToNavbar('tag', $tag->ID);
    }

    if (GetVars('AddNavbar', 'POST') == 1) {
        $zbp->AddItemToNavbar('tag', $tag->ID, $tag->Name, $tag->Url);
    }

    $zbp->AddBuildModule('tags');

    foreach ($GLOBALS['hooks']['Filter_Plugin_PostTag_Succeed'] as $fpname => &$fpsignal) {
        $fpname($tag);
    }

    return $tag;
}

/**
 * 删除标签.
 *
 * @return bool
 */
function DelTag()
{
    global $zbp;

    $tagid = (int) GetVars('id', 'GET');
    $tag = $zbp->GetTagByID($tagid);
    if ($tag->ID > 0) {
        $tag->Del();
        $zbp->DelItemToNavbar('tag', $tag->ID);
        $zbp->AddBuildModule('tags');
        foreach ($GLOBALS['hooks']['Filter_Plugin_DelTag_Succeed'] as $fpname => &$fpsignal) {
            $fpname($tag);
        }
    }

    return true;
}

//###############################################################################################################

/**
 * 提交用户数据.
 *
 * @throws Exception
 *
 * @return bool
 */
function PostMember()
{
    global $zbp;
    $mem = new Member();

    $data = array();

    if (!isset($_POST['ID'])) {
        return false;
    }

    //检测密码
    if (trim($_POST["Password"]) == '' || trim($_POST["PasswordRe"]) == '' || $_POST["Password"] != $_POST["PasswordRe"]) {
        unset($_POST["Password"]);
        unset($_POST["PasswordRe"]);
    }

    $data['ID'] = $_POST['ID'];
    $editableField = array('Password', 'Email', 'HomePage', 'Alias', 'Intro', 'Template');
    // 如果是管理员，则再允许改动别的字段
    if ($zbp->CheckRights('MemberAll')) {
        array_push($editableField, 'Level', 'Status', 'Name', 'IP');
    } else {
        $data['ID'] = $zbp->user->ID;
    }
    // 复制一个新数组
    foreach ($editableField as $value) {
        if (isset($_POST[$value])) {
            $data[$value] = GetVars($value, 'POST');
        }
    }

    if (isset($data['Name'])) {
        // 检测同名
        $m = $zbp->GetMemberByName($data['Name']);
        if ($m->ID > 0 && $m->ID != $data['ID']) {
            $zbp->ShowError(62, __FILE__, __LINE__);
        }
    }

    if (isset($data['Alias'])) {
        $data['Alias'] = FormatString($data['Alias'], '[noscript]');
    }

    if ($data['ID'] == 0) {
        if (!isset($data['Password']) || $data['Password'] == '') {
            $zbp->ShowError(73, __FILE__, __LINE__);
        }
        $data['IP'] = GetGuestIP();
        if ($mem->Guid == '') {
            $mem->Guid = GetGuid();
        }
    } else {
        $mem = $zbp->GetMemberByID($data['ID']);
    }

    foreach ($zbp->datainfo['Member'] as $key => $value) {
        if ($key == 'ID' || $key == 'Meta') {
            continue;
        }
        if (isset($data[$key])) {
            $mem->$key = $data[$key];
        }
    }

    // 然后，读入密码
    // 密码需要单独处理，因为拿不到用户Guid
    if (isset($data['Password'])) {
        if ($data['Password'] != '') {
            if (strlen($data['Password']) < $zbp->option['ZC_PASSWORD_MIN'] || strlen($data['Password']) > $zbp->option['ZC_PASSWORD_MAX']) {
                $zbp->ShowError(54, __FILE__, __LINE__);
            }
            if (!CheckRegExp($data['Password'], '[password]')) {
                $zbp->ShowError(54, __FILE__, __LINE__);
            }
            $mem->Password = Member::GetPassWordByGuid($data['Password'], $mem->Guid);
        }
    }

    FilterMeta($mem);

    foreach ($GLOBALS['hooks']['Filter_Plugin_PostMember_Core'] as $fpname => &$fpsignal) {
        $fpname($mem);
    }

    FilterMember($mem);

    CountMember($mem, array(null, null, null, null));

    // 查询同名
    if (isset($data['Name'])) {
        if ($data['ID'] == 0) {
            if ($zbp->CheckMemberNameExist($data['Name'])) {
                $zbp->ShowError(62, __FILE__, __LINE__);
            }
        }
    }

    $mem->Save();
    $zbp->AddCache($mem);

    $zbp->AddBuildModule('authors');

    foreach ($GLOBALS['hooks']['Filter_Plugin_PostMember_Succeed'] as $fpname => &$fpsignal) {
        $fpname($mem);
    }

    return $mem;
}

/**
 * 删除用户.
 *
 * @return bool
 */
function DelMember()
{
    global $zbp;

    $id = (int) GetVars('id', 'GET');
    $mem = $zbp->GetMemberByID($id);
    if ($mem->ID > 0 && $mem->ID != $zbp->user->ID) {
        if ($mem->IsGod !== true) {
            DelMember_AllData($id);
            $mem->Del();
            foreach ($GLOBALS['hooks']['Filter_Plugin_DelMember_Succeed'] as $fpname => &$fpsignal) {
                $fpname($mem);
            }
        }
    } else {
        return false;
    }

    return true;
}

/**
 * 删除用户下所有数据（包括文章、评论、附件）.
 *
 * @param int $id 用户ID
 */
function DelMember_AllData($id)
{
    global $zbp;

    $w = array();
    $w[] = array('=', 'log_AuthorID', $id);

    /* @var Post[] $articles */
    $articles = $zbp->GetPostList('*', $w);
    foreach ($articles as $a) {
        $a->Del();
    }

    $w = array();
    $w[] = array('=', 'comm_AuthorID', $id);
    /* @var Comment[] $comments */
    $comments = $zbp->GetCommentList('*', $w);
    foreach ($comments as $c) {
        $c->AuthorID = 0;
        $c->Save();
    }

    $w = array();
    $w[] = array('=', 'ul_AuthorID', $id);
    /* @var Upload[] $uploads */
    $uploads = $zbp->GetUploadList('*', $w);
    foreach ($uploads as $u) {
        $u->Del();
        $u->DelFile();
    }
}

//###############################################################################################################

/**
 * 提交模块数据.
 *
 * @return bool
 */
function PostModule()
{
    global $zbp;

    if (isset($_POST['catalog_style'])) {
        $zbp->option['ZC_MODULE_CATALOG_STYLE'] = $_POST['catalog_style'];
        $zbp->SaveOption();
    }

    if ($_POST['FileName'] == 'archives') {
        if (isset($_POST['archives_style'])) {
            $zbp->option['ZC_MODULE_ARCHIVES_STYLE'] = 1;
        } else {
            $zbp->option['ZC_MODULE_ARCHIVES_STYLE'] = 0;
        }
        $zbp->SaveOption();
    }

    if (!isset($_POST['ID'])) {
        return false;
    }

    if (!GetVars('FileName', 'POST')) {
        $_POST['FileName'] = 'mod' . rand(1000, 9999);
    } else {
        $_POST['FileName'] = strtolower($_POST['FileName']);
    }
    if (!GetVars('HtmlID', 'POST')) {
        $_POST['HtmlID'] = $_POST['FileName'];
    }
    if (isset($_POST['MaxLi'])) {
        $_POST['MaxLi'] = (int) $_POST['MaxLi'];
    }
    if (isset($_POST['IsHideTitle'])) {
        $_POST['IsHideTitle'] = (int) $_POST['IsHideTitle'];
    }
    if (!isset($_POST['Type'])) {
        $_POST['Type'] = 'div';
    }
    if (isset($_POST['Content'])) {
        if ($_POST['Type'] != 'div') {
            $_POST['Content'] = str_replace(array("\r", "\n"), array('', ''), $_POST['Content']);
        }
    }

    /* @var Module $mod */
    $mod = $zbp->GetModuleByID(GetVars('ID', 'POST'));

    foreach ($zbp->datainfo['Module'] as $key => $value) {
        if ($key == 'ID' || $key == 'Meta') {
            continue;
        }
        if (isset($_POST[$key])) {
            $mod->$key = GetVars($key, 'POST');
        }
    }

    if (isset($_POST['NoRefresh'])) {
        $mod->NoRefresh = (bool) $_POST['NoRefresh'];
    }

    FilterMeta($mod);

    foreach ($GLOBALS['hooks']['Filter_Plugin_PostModule_Core'] as $fpname => &$fpsignal) {
        $fpname($mod);
    }

    FilterModule($mod);

    //不能新存themeinclude
    if ($mod->SourceType == 'themeinclude') {
        $f = $zbp->usersdir . 'theme/' . $zbp->theme . '/include/' . $mod->FileName . '.php';
        if (!file_exists($f)) {
            return false;
        }
    }

    $mod->Save();
    $zbp->AddCache($mod);

    if ((int) GetVars('ID', 'POST') > 0) {
        $zbp->AddBuildModule($mod->FileName);
    }

    foreach ($GLOBALS['hooks']['Filter_Plugin_PostModule_Succeed'] as $fpname => &$fpsignal) {
        $fpname($mod);
    }

    return $mod;
}

/**
 * 删除模块.
 *
 * @return bool
 */
function DelModule()
{
    global $zbp;

    if (GetVars('source', 'GET') == 'theme') {
        $fn = GetVars('filename', 'GET');
        if ($fn) {
            $mod = $zbp->GetModuleByFileName($fn);
            if ($mod->FileName == $fn) {
                $mod->Del();
                foreach ($GLOBALS['hooks']['Filter_Plugin_DelModule_Succeed'] as $fpname => &$fpsignal) {
                    $fpname($mod);
                }

                return true;
            }
            unset($mod);
        }

        return false;
    }

    $id = (int) GetVars('id', 'GET');
    $mod = $zbp->GetModuleByID($id);
    if ($mod->Source != 'system') {
        $mod->Del();
        foreach ($GLOBALS['hooks']['Filter_Plugin_DelModule_Succeed'] as $fpname => &$fpsignal) {
            $fpname($mod);
        }
    } else {
        return false;
    }
    unset($mod);

    return true;
}

//###############################################################################################################

/**
 * 附件上传.
 *
 * @throws Exception
 */
function PostUpload()
{
    global $zbp;

    foreach ($_FILES as $key => $value) {
        if ($_FILES[$key]['error'] == 0) {
            if (is_uploaded_file($_FILES[$key]['tmp_name'])) {
                $upload = new Upload();
                $upload->Name = $_FILES[$key]['name'];
                if (GetVars('auto_rename', 'POST') == 'on' || GetVars('auto_rename', 'POST') == true) {
                    $temp_arr = explode(".", $upload->Name);
                    $file_ext = strtolower(trim(array_pop($temp_arr)));
                    $upload->Name = date("YmdHis") . time() . rand(10000, 99999) . '.' . $file_ext;
                }
                $upload->SourceName = $_FILES[$key]['name'];
                $upload->MimeType = $_FILES[$key]['type'];
                $upload->Size = $_FILES[$key]['size'];
                $upload->AuthorID = $zbp->user->ID;

                //检查同月重名
                $d1 = date('Y-m-01', time());
                $d2 = date('Y-m-d', strtotime(date('Y-m-01', time()) . ' +1 month -1 day'));
                $d1 = strtotime($d1);
                $d2 = strtotime($d2);
                $w = array();
                $w[] = array('=', 'ul_Name', $upload->Name);
                $w[] = array('>=', 'ul_PostTime', $d1);
                $w[] = array('<=', 'ul_PostTime', $d2);
                $uploads = $zbp->GetUploadList('*', $w);
                if (count($uploads) > 0) {
                    $zbp->ShowError(28, __FILE__, __LINE__);
                }

                if (!$upload->CheckExtName()) {
                    $zbp->ShowError(26, __FILE__, __LINE__);
                }

                if (!$upload->CheckSize()) {
                    $zbp->ShowError(27, __FILE__, __LINE__);
                }

                $upload->SaveFile($_FILES[$key]['tmp_name']);
                $upload->Save();
                $zbp->AddCache($upload);
            }
        }
    }
    if (isset($upload)) {
        CountMemberArray(array($upload->AuthorID), array(0, 0, 0, +1));
    }

    foreach ($GLOBALS['hooks']['Filter_Plugin_PostUpload_Succeed'] as $fpname => &$fpsignal) {
        $fpname($upload);
    }

    return $upload;
}

/**
 * 删除附件.
 *
 * @return bool
 */
function DelUpload()
{
    global $zbp;

    $id = (int) GetVars('id', 'GET');
    $u = $zbp->GetUploadByID($id);
    if ($zbp->CheckRights('UploadAll') || (!$zbp->CheckRights('UploadAll') && $u->AuthorID == $zbp->user->ID)) {
        $u->Del();
        CountMemberArray(array($u->AuthorID), array(0, 0, 0, -1));
        $u->DelFile();
    } else {
        return false;
    }

    return true;
}

//###############################################################################################################

/**
 * 启用插件.
 *
 * @param string $name 插件ID
 *
 * @throws Exception
 *
 * @return string 返回插件ID
 */
function EnablePlugin($name)
{
    global $zbp;

    $app = $zbp->LoadApp('plugin', $name);
    $app->CheckCompatibility_Global('Enable');
    $app->CheckCompatibility();

    $zbp->option['ZC_USING_PLUGIN_LIST'] = AddNameInString($zbp->option['ZC_USING_PLUGIN_LIST'], $name);

    $array = explode('|', $zbp->option['ZC_USING_PLUGIN_LIST']);
    $arrayhas = array();
    foreach ($array as $p) {
        if (is_readable($zbp->usersdir . 'plugin/' . $p . '/plugin.xml')) {
            $arrayhas[] = $p;
        }
    }

    $zbp->option['ZC_USING_PLUGIN_LIST'] = trim(implode('|', $arrayhas), '|');

    $zbp->SaveOption();

    return $name;
}

/**
 * 禁用插件.
 *
 * @param string $name 插件ID
 *
 * @return App|bool
 */
function DisablePlugin($name)
{
    global $zbp;

    $app = $zbp->LoadApp('plugin', $name);
    $app->CheckCompatibility_Global('Disable');

    UninstallPlugin($name);
    $zbp->option['ZC_USING_PLUGIN_LIST'] = DelNameInString($zbp->option['ZC_USING_PLUGIN_LIST'], $name);

    $array = explode('|', $zbp->option['ZC_USING_PLUGIN_LIST']);
    $arrayhas = array();
    foreach ($array as $p) {
        if (is_readable($zbp->usersdir . 'plugin/' . $p . '/plugin.xml')) {
            $arrayhas[] = $p;
        }
    }

    $zbp->option['ZC_USING_PLUGIN_LIST'] = trim(implode('|', $arrayhas), '|');

    $zbp->SaveOption();

    return true;
}

/**
 * 设置当前主题样式.
 *
 * @param string $theme 主题ID
 * @param string $style 样式名
 *
 * @throws Exception
 *
 * @return string 返回主题ID
 */
function SetTheme($theme, $style)
{
    global $zbp;

    $app = $zbp->LoadApp('theme', $theme);
    $app->CheckCompatibility_Global('Enable');
    $app->CheckCompatibility();

    $oldTheme = $zbp->option['ZC_BLOG_THEME'];
    $old = $zbp->LoadApp('theme', $oldTheme);
    if ($theme != $oldTheme) {
        $old->CheckCompatibility_Global('Disable');
    }

    if ($theme != $oldTheme && $old->isloaded == true) {
        $old->SaveSideBars();
    }

    $zbp->option['ZC_BLOG_THEME'] = $theme;
    $zbp->option['ZC_BLOG_CSS'] = $style;
    if ($theme != $oldTheme) {
        $app->LoadSideBars();
    } else {
        $app->SaveSideBars();
    }

    $zbp->SaveOption();
    //del oldtheme SideBars cache
    $aa = array();
    foreach ($zbp->cache as $key => $value) {
        if (stripos($value, 'sidebars_') !== false) {
            $aa[] = substr($value, 9);
        }
    }
    foreach ($aa as $key => $value) {
        $a = $zbp->LoadApp('theme', $value);
        if ($a->isloaded == false) {
            $zbp->cache->DelKey('sidebars_' . $a->id);
        }
    }
    $zbp->SaveCache();

    if ($oldTheme != $theme) {
        UninstallPlugin($oldTheme);
    }

    return $theme;
}

/**
 * 设置侧栏.
 */
function SetSidebar()
{
    global $zbp;
    for ($i = 1; $i <= 9; $i++) {
        $optionName = $i === 1 ? 'ZC_SIDEBAR_ORDER' : "ZC_SIDEBAR${i}_ORDER";
        $formName = $i === 1 ? 'sidebar' : "sidebar${i}";
        $zbp->option[$optionName] = trim(GetVars($formName, 'POST'), '|');
    }
    $zbp->SaveOption();
}

/**
 * 保存网站设置选项.
 *
 * @throws Exception
 */
function SaveSetting()
{
    global $zbp;

    foreach ($_POST as $key => $value) {
        if (substr($key, 0, 2) !== 'ZC') {
            continue;
        }

        if ($key == 'ZC_PERMANENT_DOMAIN_ENABLE'
            || $key == 'ZC_COMMENT_TURNOFF'
            || $key == 'ZC_COMMENT_REVERSE_ORDER'
            || $key == 'ZC_COMMENT_AUDIT'
            || $key == 'ZC_DISPLAY_SUBCATEGORYS'
            || $key == 'ZC_GZIP_ENABLE'
            || $key == 'ZC_SYNTAXHIGHLIGHTER_ENABLE'
            || $key == 'ZC_COMMENT_VERIFY_ENABLE'
            || $key == 'ZC_CLOSE_SITE'
            || $key == 'ZC_ADDITIONAL_SECURITY'
            || $key == 'ZC_ARTICLE_THUMB_SWITCH'
        ) {
            $zbp->option[$key] = (bool) $value;
            continue;
        }
        if ($key == 'ZC_RSS2_COUNT'
            || $key == 'ZC_UPLOAD_FILESIZE'
            || $key == 'ZC_DISPLAY_COUNT'
            || $key == 'ZC_SEARCH_COUNT'
            || $key == 'ZC_PAGEBAR_COUNT'
            || $key == 'ZC_COMMENTS_DISPLAY_COUNT'
            || $key == 'ZC_MANAGE_COUNT'
            || $key == 'ZC_ARTICLE_THUMB_TYPE'
            || $key == 'ZC_ARTICLE_THUMB_WIDTH'
            || $key == 'ZC_ARTICLE_THUMB_HEIGHT'
        ) {
            $zbp->option[$key] = (int) $value;
            continue;
        }
        if ($key == 'ZC_UPLOAD_FILETYPE') {
            $value = strtolower($value);
            $value = str_replace(array(' ','　'), '', $value);
            $value = DelNameInString($value, 'php');
            $value = DelNameInString($value, 'asp');
        }
        $zbp->option[$key] = trim(str_replace(array("\r", "\n"), array("", ""), $value));
    }
    $zbp->option['ZC_DEBUG_MODE'] = (bool) $zbp->option['ZC_DEBUG_MODE'];

    if ($zbp->option['ZC_DEBUG_MODE']) {
        $zbp->option['ZC_DEBUG_MODE'] = true;
        $zbp->option['ZC_DEBUG_MODE_STRICT'] = true;
        $zbp->option['ZC_DEBUG_MODE_WARNING'] = true;
        $zbp->option['ZC_DEBUG_LOG_ERROR'] = true;
    } else {
        $zbp->option['ZC_DEBUG_MODE'] = false;
        $zbp->option['ZC_DEBUG_MODE_STRICT'] = false;
        $zbp->option['ZC_DEBUG_LOG_ERROR'] = false;
    }

    $zbp->option['ZC_BLOG_HOST'] = trim($zbp->option['ZC_BLOG_HOST']);
    $zbp->option['ZC_BLOG_HOST'] = trim($zbp->option['ZC_BLOG_HOST'], '/') . '/';
    if ($zbp->option['ZC_BLOG_HOST'] == '/') {
        $zbp->option['ZC_BLOG_HOST'] = $zbp->host;
    }
    $usePC = false;
    for ($i = 0; $i < (strlen($zbp->option['ZC_BLOG_HOST']) - 1); $i++) {
        $l = substr($zbp->option['ZC_BLOG_HOST'], $i, 1);
        if (ord($l) >= 192) {
            $usePC = true;
        }
    }
    if ($usePC && function_exists('mb_strtolower')) {
        $Punycode = new Punycode();
        $zbp->option['ZC_BLOG_HOST'] = $Punycode->encode($zbp->option['ZC_BLOG_HOST']);
    }
    $lang = include $zbp->usersdir . 'language/' . $zbp->option['ZC_BLOG_LANGUAGEPACK'] . '.php';
    $zbp->option['ZC_BLOG_LANGUAGE'] = $lang['lang'];
    $zbp->option['ZC_BLOG_PRODUCT'] = 'Z-BlogPHP';
    $zbp->SaveOption();

    return true;
}

//###############################################################################################################

/**
 * 显示404页面(内置插件函数).
 *
 * 可通过主题中的404.php模板自定义显示效果
 *
 * @param $errorCode
 * @param $errorDescription
 * @param $file
 * @param $line
 *
 * @api Filter_Plugin_Zbp_ShowError
 *
 * @throws Exception
 */
function Include_ShowError404($errorCode, $errorDescription, $file, $line)
{
    global $zbp;
    if (!in_array("Status: 404 Not Found", headers_list())) {
        return;
    }

    $zbp->template->SetTags('title', $zbp->title);
    $zbp->template->SetTemplate('404');
    $zbp->template->Display();

    exit;
}

/**
 * 输出后台指定字体family(内置插件函数).
 */
function Include_AddonAdminFont()
{
    global $zbp;
    $f = $s = '';
    if (isset($zbp->lang['font_family']) && trim($zbp->lang['font_family'])) {
        $f = 'font-family:' . $zbp->lang['font_family'] . ';';
    }

    if (isset($zbp->lang['font_size']) && trim($zbp->lang['font_size'])) {
        $s = 'font-size:' . $zbp->lang['font_size'] . ';';
    }

    if ($f || $s) {
        echo '<style type="text/css">body{' . $s . $f . '}</style>';
    }
}

/**
 * 批处理文章
 *
 * @param int $type
 */
function Include_BatchPost_Article($type)
{
    global $zbp;
    if ($type != ZC_POST_TYPE_ARTICLE) {
        return;
    }
    if (!isset($_POST['id'])) {
        return;
    }
    $arrayid = $_POST['id'];
    foreach ($arrayid as $key => $value) {
        $id = (int) $value;
        $article = new Post();
        $article = $zbp->GetPostByID($id);
        if ($article->ID > 0) {
            if (!$zbp->CheckRights('ArticleAll') && $article->AuthorID != $zbp->user->ID) {
                continue;
            }

            $pre_author = $article->AuthorID;
            $pre_tag = $article->Tag;
            $pre_category = $article->CateID;
            $pre_istop = $article->IsTop;
            $pre_status = $article->Status;

            $article->Del();

            DelArticle_Comments($article->ID);

            CountTagArrayString($pre_tag, -1, $article->ID);
            CountMemberArray(array($pre_author), array(-1, 0, 0, 0));
            CountCategoryArray(array($pre_category), -1);
            if (($pre_istop == 0 && $pre_status == 0)) {
                CountNormalArticleNums(-1);
            }
            if ($article->IsTop == true) {
                CountTopPost($article->Type, null, $article->ID);
            }

            foreach ($GLOBALS['hooks']['Filter_Plugin_DelArticle_Succeed'] as $fpname => &$fpsignal) {
                $fpname($article);
            }
        }
    }
    $zbp->AddBuildModule('previous');
    $zbp->AddBuildModule('calendar');
    $zbp->AddBuildModule('comments');
    $zbp->AddBuildModule('archives');
    $zbp->AddBuildModule('tags');
    $zbp->AddBuildModule('authors');

    return true;
}

/**
 * 批处理页面
 *
 * @param int $type
 */
function Include_BatchPost_Page($type)
{
    global $zbp;
    if ($type != ZC_POST_TYPE_PAGE) {
        return;
    }
    if (!isset($_POST['id'])) {
        return;
    }
    $arrayid = $_POST['id'];
    foreach ($arrayid as $key => $value) {
        $id = (int) $value;
        $article = new Post();
        $article = $zbp->GetPostByID($id);
        if ($article->ID > 0) {
            if (!$zbp->CheckRights('PageAll') && $article->AuthorID != $zbp->user->ID) {
                continue;
            }

            $pre_author = $article->AuthorID;

            $article->Del();

            DelArticle_Comments($article->ID);

            CountMemberArray(array($pre_author), array(0, -1, 0, 0));

            $zbp->AddBuildModule('comments');

            $zbp->DelItemToNavbar('page', $article->ID);

            foreach ($GLOBALS['hooks']['Filter_Plugin_DelPage_Succeed'] as $fpname => &$fpsignal) {
                $fpname($article);
            }
        }
    }
    return true;
}

/**
 * 首页index.php的结尾处理
 */
function Include_Index_End()
{
    global $zbp;
    if ($zbp->option['ZC_RUNINFO_DISPLAY'] == true) {
        RunTime();
    }
}

/**
 * 首页index.php的开头处理
 */
function Include_Index_Begin()
{
    global $zbp;
    $zbp->CheckSiteClosed();

    $zbp->RedirectPermanentDomain();

    if ($zbp->template->hasTemplate('404')) {
        Add_Filter_Plugin('Filter_Plugin_Zbp_ShowError', 'Include_ShowError404');
    }

    if ($zbp->option['ZC_ADDITIONAL_SECURITY']) {
        header('X-XSS-Protection: 1; mode=block');
        if ($zbp->isHttps) {
            header('Upgrade-Insecure-Requests: 1');
        }
    }
}


/**
 * “审核中会员”的前台权限拒绝验证
 */
function Include_Frontend_CheckRights($action, $level)
{
    global $zbp;
    if ($zbp->user->Status == ZC_MEMBER_STATUS_AUDITING) {
        if (!in_array($action, array('login', 'logout', 'misc', 'feed', 'ajax', 'verify', 'NoValidCode', 'MemberEdt', 'MemberPst', 'MemberMng'))) {
            $GLOBALS['hooks']['Filter_Plugin_Zbp_CheckRights']['Include_Frontend_CheckRights'] = PLUGIN_EXITSIGNAL_RETURN;
            return false;
        }
        if ($zbp->option['ZC_ALLOW_AUDITTING_MEMBER_VISIT_MANAGE'] == false && $action == 'admin') {
            $GLOBALS['hooks']['Filter_Plugin_Zbp_CheckRights']['Include_Frontend_CheckRights'] = PLUGIN_EXITSIGNAL_RETURN;
            return false;
        }
    }
}

//###############################################################################################################

/**
 * 过滤扩展数据.
 *
 * @param $object
 */
function FilterMeta(&$object)
{
    //$type=strtolower(get_class($object));

    foreach ($_POST as $key => $value) {
        if (substr($key, 0, 5) == 'meta_') {
            $name = substr($key, (5 - strlen($key)));
            $object->Metas->$name = $value;
        }
    }

    foreach ($object->Metas->GetData() as $key => $value) {
        if ($value == '') {
            $object->Metas->Del($key);
        }
    }
}

/**
 * 过滤评论数据.
 *
 * @param $comment
 *
 * @throws Exception
 */
function FilterComment(&$comment)
{
    global $zbp;

    if (!CheckRegExp($comment->Name, '[nickname]')) {
        $zbp->ShowError(15, __FILE__, __LINE__);
    }
    if ($comment->Email && (!CheckRegExp($comment->Email, '[email]'))) {
        $zbp->ShowError(29, __FILE__, __LINE__);
    }
    if ($comment->HomePage && (!CheckRegExp($comment->HomePage, '[homepage]'))) {
        $zbp->ShowError(30, __FILE__, __LINE__);
    }

    $comment->Name = FormatString($comment->Name, '[nohtml]');
    $comment->Name = str_replace(array('<', '>', ' ', '　'), '', $comment->Name);
    $comment->Name = SubStrUTF8_Start($comment->Name, 0, $zbp->option['ZC_USERNAME_MAX']);
    $comment->Email = SubStrUTF8_Start($comment->Email, 0, $zbp->option['ZC_EMAIL_MAX']);
    $comment->HomePage = SubStrUTF8_Start($comment->HomePage, 0, $zbp->option['ZC_HOMEPAGE_MAX']);

    $comment->Content = FormatString($comment->Content, '[nohtml]');

    $comment->Content = SubStrUTF8_Start($comment->Content, 0, 1000);
    $comment->Content = trim($comment->Content);
    if (strlen($comment->Content) == 0) {
        $zbp->ShowError(46, __FILE__, __LINE__);
    }
}

/**
 * 过滤文章数据.
 *
 * @param $article
 */
function FilterPost(&$article)
{
    global $zbp;

    $article->Title = strip_tags($article->Title);
    $article->Title = htmlspecialchars($article->Title);
    $article->Alias = FormatString($article->Alias, '[normalname]');
    $article->Alias = str_replace(' ', '', $article->Alias);
    $article->Alias = str_replace('　', '', $article->Alias);

    if ($article->Type == ZC_POST_TYPE_ARTICLE) {
        if (!$zbp->CheckRights('ArticleAll')) {
            $article->Content = FormatString($article->Content, '[noscript]');
            $article->Intro = FormatString($article->Intro, '[noscript]');
        }
    } elseif ($article->Type == ZC_POST_TYPE_PAGE) {
        if (!$zbp->CheckRights('PageAll')) {
            $article->Content = FormatString($article->Content, '[noscript]');
            $article->Intro = FormatString($article->Intro, '[noscript]');
        }
    } else {
        if (!$zbp->CheckRights('ArticleAll')) {
            $article->Content = FormatString($article->Content, '[noscript]');
            $article->Intro = FormatString($article->Intro, '[noscript]');
        }
    }
}

/**
 * 过滤用户数据.
 *
 * @param $member
 *
 * @throws Exception
 */
function FilterMember(&$member)
{
    global $zbp;
    $member->Intro = FormatString($member->Intro, '[noscript]');
    $member->Alias = FormatString($member->Alias, '[normalname]');
    $member->Alias = str_replace(array('/', '.', ' ', '　', '_'), '', $member->Alias);
    $member->Alias = SubStrUTF8_Start($member->Alias, 0, (int) $zbp->datainfo['Member']['Alias'][2]);
    if (strlen($member->Name) < $zbp->option['ZC_USERNAME_MIN'] || strlen($member->Name) > $zbp->option['ZC_USERNAME_MAX']) {
        $zbp->ShowError(77, __FILE__, __LINE__);
    }

    if (!CheckRegExp($member->Name, '[username]')) {
        $zbp->ShowError(77, __FILE__, __LINE__);
    }

    if ($member->Alias != '' && !CheckRegExp($member->Alias, '[nickname]')) {
        $zbp->ShowError(90, __FILE__, __LINE__);
    }

    if (!CheckRegExp($member->Email, '[email]')) {
        $member->Email = 'null@null.com';
    }
    $member->Email = strtolower($member->Email);

    if (substr($member->HomePage, 0, 4) != 'http') {
        $member->HomePage = 'http://' . $member->HomePage;
    }

    if (!CheckRegExp($member->HomePage, '[homepage]')) {
        $member->HomePage = '';
    }

    if (strlen($member->Email) > $zbp->option['ZC_EMAIL_MAX']) {
        $zbp->ShowError(29, __FILE__, __LINE__);
    }

    if (strlen($member->HomePage) > $zbp->option['ZC_HOMEPAGE_MAX']) {
        $zbp->ShowError(30, __FILE__, __LINE__);
    }
}

/**
 * 过滤模块数据.
 *
 * @param $module
 */
function FilterModule(&$module)
{
    global $zbp;
    $module->FileName = FormatString($module->FileName, '[filename]');
    $module->HtmlID = FormatString($module->HtmlID, '[normalname]');
}

/**
 * 过滤分类数据.
 *
 * @param $category
 */
function FilterCategory(&$category)
{
    global $zbp;
    $category->Name = strip_tags($category->Name);
    $category->Name = trim($category->Name);
    $category->Alias = FormatString($category->Alias, '[normalname]');
    //$category->Alias=str_replace('/','',$category->Alias);
    $category->Alias = str_replace('.', '', $category->Alias);
    $category->Alias = str_replace(' ', '', $category->Alias);
    $category->Alias = str_replace('_', '', $category->Alias);
    $category->Alias = trim($category->Alias);
}

/**
 * 过滤tag数据.
 *
 * @param $tag
 */
function FilterTag(&$tag)
{
    global $zbp;
    $tag->Name = strip_tags($tag->Name);
    $tag->Name = trim($tag->Name);
    $tag->Alias = FormatString($tag->Alias, '[normalname]');
    $tag->Alias = str_replace('.', '', $tag->Alias);
    $tag->Alias = str_replace(' ', '', $tag->Alias);
    $tag->Alias = str_replace('_', '', $tag->Alias);
    $tag->Alias = trim($tag->Alias);
}

//###############################################################################################################
//统计函数

/**
 *统计置顶文章数组.
 *
 * @param int  $type
 * @param null $addplus
 * @param null $delplus
 */
function CountTopPost($type = 0, $addplus = null, $delplus = null)
{
    global $zbp;
    $varname = 'top_post_array_' . $type;
    $array = unserialize($zbp->cache->$varname);
    if (!is_array($array)) {
        $array = array();
    }

    if ($addplus === null && $delplus === null) {
        $s = $zbp->db->sql->Select($zbp->table['Post'], 'log_ID', array(array('=', 'log_Type', $type), array('>', 'log_IsTop', 0), array('=', 'log_Status', 0)), null, null, null);
        $a = $zbp->db->Query($s);
        foreach ($a as $id) {
            $array[(int) current($id)] = (int) current($id);
        }
    } elseif ($addplus !== null && $delplus === null) {
        $addplus = (int) $addplus;
        $array[$addplus] = $addplus;
    } elseif ($addplus === null && $delplus !== null) {
        $delplus = (int) $delplus;
        unset($array[$delplus]);
    }

    $zbp->cache->$varname = serialize($array);
}

/**
 *统计评论数.
 *
 * @param int $allplus 控制是否要进行全表扫描 总评论
 * @param int $chkplus 控制是否要进行全表扫描 未审核评论
 */
function CountCommentNums($allplus = null, $chkplus = null)
{
    global $zbp;

    if ($allplus === null) {
        $zbp->cache->all_comment_nums = (int) GetValueInArrayByCurrent($zbp->db->sql->get()->select($GLOBALS['table']['Comment'])->count(array('*' => 'num'))->query, 'num');
    } else {
        $zbp->cache->all_comment_nums += $allplus;
    }
    if ($chkplus === null) {
        $zbp->cache->check_comment_nums = (int) GetValueInArrayByCurrent($zbp->db->sql->get()->select($GLOBALS['table']['Comment'])->count(array('*' => 'num'))->where('=', 'comm_Ischecking', '1')->query, 'num');
    } else {
        $zbp->cache->check_comment_nums += $chkplus;
    }
    $zbp->cache->normal_comment_nums = (int) ($zbp->cache->all_comment_nums - $zbp->cache->check_comment_nums);
}

/**
 *统计公开文章数.
 *
 * @param int $plus 控制是否要进行全表扫描
 */
function CountNormalArticleNums($plus = null)
{
    global $zbp;

    if ($plus === null) {
        $s = $zbp->db->sql->Count($zbp->table['Post'], array(array('COUNT', '*', 'num')), array(array('=', 'log_Type', 0), array('=', 'log_Status', 0)));
        $num = GetValueInArrayByCurrent($zbp->db->Query($s), 'num');

        $zbp->cache->normal_article_nums = $num;
    } else {
        $zbp->cache->normal_article_nums += $plus;
    }
}

/**
 * 统计文章下评论数.
 *
 * @param post $article
 * @param int  $plus    控制是否要进行全表扫描
 */
function CountPost(&$article, $plus = null)
{
    global $zbp;

    if ($plus === null) {
        $id = $article->ID;

        $s = $zbp->db->sql->Count($zbp->table['Comment'], array(array('COUNT', '*', 'num')), array(array('=', 'comm_LogID', $id), array('=', 'comm_IsChecking', 0)));
        $num = GetValueInArrayByCurrent($zbp->db->Query($s), 'num');

        $article->CommNums = $num;
    } else {
        $article->CommNums += $plus;
    }
}

/**
 * 批量统计指定文章下评论数并保存.
 *
 * @param array $array 记录文章ID的数组
 * @param int   $plus  控制是否要进行全表扫描
 * @param int      $type      post和category的分类Type
 */
function CountPostArray($array, $plus = null, $type = 0)
{
    global $zbp;
    $array = array_unique($array);
    foreach ($array as $value) {
        if ($value == 0) {
            continue;
        }

        $article = $zbp->GetPostByID($value);
        if ($article->ID > 0) {
            CountPost($article, $plus, $type);
            $article->Save();
        }
    }
}

/**
 * 统计分类下文章数.
 *
 * @param Category &$category
 * @param int      $plus      控制是否要进行全表扫描
 * @param int      $type      post和category的分类Type
 */
function CountCategory(&$category, $plus = null, $type = 0)
{
    global $zbp;

    if ($plus === null) {
        $id = $category->ID;

        $s = $zbp->db->sql->Count($zbp->table['Post'], array(array('COUNT', '*', 'num')), array(array('=', 'log_Type', $type), array('=', 'log_Status', 0), array('=', 'log_CateID', $id)));
        $num = GetValueInArrayByCurrent($zbp->db->Query($s), 'num');

        $category->Count = $num;
    } else {
        $category->Count += $plus;
    }
}

/**
 * 批量统计指定分类下文章数并保存.
 *
 * @param array $array 记录分类ID的数组
 * @param int   $plus  控制是否要进行全表扫描
 * @param int   $type  post和category的分类Type
 */
function CountCategoryArray($array, $plus = null, $type = 0)
{
    global $zbp;
    $array = array_unique($array);
    foreach ($array as $value) {
        if ($value == 0) {
            continue;
        }
        if (isset($zbp->categories_all[$value])) {
            CountCategory($zbp->categories_all[$value], $plus, $type);
            $zbp->categories_all[$value]->Save();
        }
    }
}

/**
 * 统计tag下的文章数.
 *
 * @param tag &$tag
 * @param int $plus 控制是否要进行全表扫描
 * @param int $type post和tag的分类Type
 */
function CountTag(&$tag, $plus = null, $type = 0)
{
    global $zbp;

    if ($plus === null) {
        $id = $tag->ID;

        $s = $zbp->db->sql->Count($zbp->table['Post'], array(array('COUNT', '*', 'num')), array('=', 'log_Type', $type), array(array('LIKE', 'log_Tag', '%{' . $id . '}%')));
        $num = GetValueInArrayByCurrent($zbp->db->Query($s), 'num');

        $tag->Count = $num;
    } else {
        $tag->Count += $plus;
    }
}

/**
 * 批量统计指定tag下文章数并保存.
 *
 * @param string $string 类似'{1}{2}{3}{4}{4}'的tagID串
 * @param int    $plus   控制是否要进行全表扫描
 * @param int    $articleid   暂没发现有用处的参数
 *
 * @return bool
 */
function CountTagArrayString($string, $plus = null, $articleid = null)
{
    global $zbp;
    /* @var Tag[] $array */
    $array = $zbp->LoadTagsByIDString($string);

    //添加大数据接口,tag,plus,id
    foreach ($GLOBALS['hooks']['Filter_Plugin_LargeData_CountTagArray'] as $fpname => &$fpsignal) {
        $fpreturn = $fpname($array, $plus, $articleid);
        if ($fpsignal == PLUGIN_EXITSIGNAL_RETURN) {
            $fpsignal = PLUGIN_EXITSIGNAL_NONE;

            return $fpreturn;
        }
    }

    foreach ($array as &$tag) {
        CountTag($tag, $plus, $tag->Type);
        $tag->Save();
    }

    return true;
}

/**
 * 统计用户下的文章数、页面数、评论数、附件数等.
 *
 * @param $member
 * @param array $plus 设置是否需要完全全表扫描
 */
function CountMember(&$member, $plus = array(null, null, null, null))
{
    global $zbp;
    if (!($member instanceof Member)) {
        return;
    }

    $id = $member->ID;

    if ($plus[0] === null) {
        $s = $zbp->db->sql->Count($zbp->table['Post'], array(array('COUNT', '*', 'num')), array(array('=', 'log_AuthorID', $id), array('=', 'log_Type', 0)));
        $member_Articles = GetValueInArrayByCurrent($zbp->db->Query($s), 'num');
        $member->Articles = $member_Articles;
    } else {
        $member->Articles += $plus[0];
    }

    if ($plus[1] === null) {
        $s = $zbp->db->sql->Count($zbp->table['Post'], array(array('COUNT', '*', 'num')), array(array('=', 'log_AuthorID', $id), array('=', 'log_Type', 1)));
        $member_Pages = GetValueInArrayByCurrent($zbp->db->Query($s), 'num');
        $member->Pages = $member_Pages;
    } else {
        $member->Pages += $plus[1];
    }

    if ($plus[2] === null) {
        if ($member->ID > 0) {
            $s = $zbp->db->sql->Count($zbp->table['Comment'], array(array('COUNT', '*', 'num')), array(array('=', 'comm_AuthorID', $id), array('=', 'comm_IsChecking', 0)));
            $member_Comments = GetValueInArrayByCurrent($zbp->db->Query($s), 'num');
            $member->Comments = $member_Comments;
        }
    } else {
        $member->Comments += $plus[2];
    }

    if ($plus[3] === null) {
        $s = $zbp->db->sql->Count($zbp->table['Upload'], array(array('COUNT', '*', 'num')), array(array('=', 'ul_AuthorID', $id)));
        $member_Uploads = GetValueInArrayByCurrent($zbp->db->Query($s), 'num');
        $member->Uploads = $member_Uploads;
    } else {
        $member->Uploads += $plus[3];
    }
}

/**
 * 批量统计指定用户数据并保存.
 *
 * @param array $array 记录用户ID的数组
 * @param array $plus  设置是否需要完全全表扫描
 */
function CountMemberArray($array, $plus = array(null, null, null, null))
{
    global $zbp;
    $array = array_unique($array);
    foreach ($array as $value) {
        if ($value == 0) {
            continue;
        }

        if (isset($zbp->members[$value])) {
            CountMember($zbp->members[$value], $plus);
            $zbp->members[$value]->Save();
        }
    }
}

//###############################################################################################################

/**
 * BuildModule_catalog
 *
 * @deprecated
 *
 * @throws Exception
 *
 * @return string
 */
function BuildModule_catalog()
{
    return ModuleBuilder::Catalog();
}

/**
 * BuildModule_calendar
 *
 * @param string $date
 *
 * @deprecated
 *
 * @throws Exception
 *
 * @return string
 */
function BuildModule_calendar($date = '')
{
    return ModuleBuilder::Calendar($date);
}

/**
 * BuildModule_comments
 *
 * @deprecated
 *
 * @throws Exception
 *
 * @return string
 */
function BuildModule_comments()
{
    return ModuleBuilder::Comments();
}

/**
 * BuildModule_previous
 *
 * @deprecated
 *
 * @throws Exception
 *
 * @return string
 */
function BuildModule_previous()
{
    return ModuleBuilder::LatestArticles();
}

/**
 * BuildModule_archives
 *
 * @deprecated
 *
 * @throws Exception
 *
 * @return string
 */
function BuildModule_archives()
{
    return ModuleBuilder::Archives();
}

/**
 * BuildModule_navbar
 *
 * @deprecated
 *
 * @throws Exception
 *
 * @return string
 */
function BuildModule_navbar()
{
    return ModuleBuilder::Navbar();
}

/**
 * BuildModule_tags
 *
 * @deprecated
 *
 * @throws Exception
 *
 * @return string
 */
function BuildModule_tags()
{
    return ModuleBuilder::TagList();
}

/**
 * BuildModule_authors
 *
 * @param int $level
 *
 * @deprecated
 *
 * @throws Exception
 *
 * @return string
 */
function BuildModule_authors($level = 4)
{
    return ModuleBuilder::Authors($level);
}

/**
 * BuildModule_statistics
 *
 * @param array $array
 *
 * @deprecated
 *
 * @throws Exception
 *
 * @return string
 */
function BuildModule_statistics($array = array())
{
    return ModuleBuilder::Statistics($array);
}

//###############################################################################################################

/**
 * API TokenVerify
 */
function ApiTokenVerify()
{
    global $zbp;

    if (!(is_subclass_of($zbp->user, 'BaseMember') && $zbp->user->Level > 0 && !empty($zbp->user->ID))) {
        // 在 API 中
        if (($auth = GetVars('HTTP_AUTHORIZATION', 'SERVER')) && (substr($auth, 0, 7) === 'Bearer ')) {
            // 获取 Authorization 头
            $api_token = substr($auth, 7);
        } else {
            // 获取（POST 或 GET 中的）请求参数
            $api_token = GetVars('token');
        }

        $user = $zbp->VerifyAPIToken($api_token);

        if ($user != null) {
            define('ZBP_IN_API_VERIFYBYTOKEN', true);
            $zbp->user = $user;
        }
    }
}

/**
 * API 报错函数
 */
function ApiDebugDisplay($error)
{
    ApiResponse(null, $error);
}

/**
 * 载入 API Mods.
 */
function ApiLoadMods(&$mods)
{
    global $zbp;

    foreach ($GLOBALS['hooks']['Filter_Plugin_API_Extend_Mods'] as $fpname => &$fpsignal) {
        $add_mods = $fpname();

        if (!is_array($add_mods)) {
            continue;
        }

        foreach ($add_mods as $mod => $file) {
            $mod = strtolower($mod);
            if (array_key_exists($mod, $mods)) {
                continue;
            }
    
            $mods[$mod] = $file;
        }
    }

    // 从 zb_system/api/ 目录中载入 mods
    foreach (GetFilesInDir(ZBP_PATH . 'zb_system/api/', 'php') as $mod => $file) {
        $mods[$mod] = $file;
    }
}

/**
 * API 响应.
 *
 * @param array|null $data
 * @param ZBlogException|null $error
 * @param int $code
 * @param string|null $message
 */
function ApiResponse($data = null, $error = null, $code = 200, $message = null)
{
    if (!empty($error)) {
        $error_info = array(
            'code' => ZBlogException::$error_id,
            'type' => $error->type,
            'message' => $error->message,
        );

        if ($GLOBALS['option']['ZC_DEBUG_MODE']) {
            $error_info['message_full'] = $error->messagefull;
            $error_info['file'] = $error->file;
            $error_info['line'] = $error->line;
        }

        if ($code === 200) {
            $code = 500;
        }
        if (empty($message)) {
            $message = 'System error: ' . $error->message;
        }
    }

    $response = array(
        'code' => $code,
        'message' => !empty($message) ? $message : 'OK',
        'data' => $data,
        'error' => empty($error) ? null : $error_info,
    );

    // 显示 Runtime 调试信息
    if (!defined('ZBP_API_IN_TEST') && $GLOBALS['option']['ZC_RUNINFO_DISPLAY']) {
        $runtime = RunTime(false);
        $runtime = array_slice($runtime, 0, 3);
        $response['runtime'] = $runtime;
    }

    foreach ($GLOBALS['hooks']['Filter_Plugin_API_Response'] as $fpname => &$fpsignal) {
        $fpname($response);
    }

    if (! defined('ZBP_API_IN_TEST')) {
        ob_end_clean();
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            SetHttpStatusCode($code);
        }
    }

    echo JsonEncode($response);

    if (empty($error) && $code !== 200) {
        // 如果 code 不为 200，又不是系统抛出的错误，再来抛出一个 Exception，适配 phpunit
        ZBlogException::SuspendErrorHook();
        throw new Exception($message, $code);
    }

    die;
}

/**
 * API 检测权限.
 *
 * @param bool $loginRequire
 * @param string $action
 */
function ApiCheckAuth($loginRequire = false, $action = 'view')
{
    // 登录认证
    if ($loginRequire && !$GLOBALS['zbp']->user->ID) {
        if (!defined('ZBP_API_IN_TEST') && !headers_sent()) {
            SetHttpStatusCode(401);
            header('Status: 401 Unauthorized');
        }

        ApiResponse(null, null, 401, $GLOBALS['lang']['error']['6']);
    }

    // 权限认证
    if (!empty($action) && !$GLOBALS['zbp']->CheckRights($action)) {
        if (!defined('ZBP_API_IN_TEST') && !headers_sent()) {
            SetHttpStatusCode(403);
            header('Status: 403 Forbidden');
        }

        ApiResponse(null, null, 403, $GLOBALS['lang']['error']['6']);
    }

    return true;
}

/**
 * API 获取指定属性的Array
 *
 * @param object $object
 * @param array $other_props 追加的属性
 * @param array $remove_props 要删除的属性
 * @param array $with_relations 要追加的关联对象
 */
function ApiGetObjectArray($object, $other_props = array(), $remove_props = array(), $with_relations = array())
{
    $array = $object->GetData();
    unset($array['Meta']);

    foreach ($GLOBALS['hooks']['Filter_Plugin_API_Get_Object_Array'] as $fpname => &$fpsignal) {
        $fpname($object, $array, $other_props, $remove_props, $with_relations);
    }

    foreach ($other_props as $key => $value) {
        $array[$value] = $object->$value;
    }
    switch (get_class($object)) {
        case 'Member':
            $remove_props[] = 'Guid';
            $remove_props[] = 'Password';
            $remove_props[] = 'IP';
            break;
        default:
            # code...
            break;
    }

    foreach ($remove_props as $key => $value) {
        unset($array[$value]);
    }
    foreach ($with_relations as $relation => $info) {
        $relation_obj = $object->$relation;
        if (is_array($relation_obj)) {
            $array[$relation] = ApiGetObjectArrayList(
                $relation_obj,
                isset($info['other_props']) ? $info['other_props'] : array(),
                isset($info['remove_props']) ? $info['remove_props'] : array(),
                isset($info['with_relations']) ? $info['with_relations'] : array()
            );
        } else {
            $array[$relation] = ApiGetObjectArray(
                $relation_obj,
                isset($info['other_props']) ? $info['other_props'] : array(),
                isset($info['remove_props']) ? $info['remove_props'] : array(),
                isset($info['with_relations']) ? $info['with_relations'] : array()
            );
        }
    }
    return $array;
}

/**
 * API 获取指定属性的Array 列表.
 *
 * @param array $list
 * @param array $other_props 追加的属性
 * @param array $remove_props 要删除的属性
 * @param array $with_relations 要追加的关联对象
 */
function ApiGetObjectArrayList($list, $other_props = array(), $remove_props = array(), $with_relations = array())
{
    global $zbp;

    if (array_key_exists('Author', $with_relations)) {
        $zbp->LoadMembersInList($list);
    }

    foreach ($list as &$object) {
        $object = ApiGetObjectArray($object, $other_props, $remove_props, $with_relations);
    }

    return $list;
}

/**
 * API 获取约束过滤条件
 * 将请求中的参数转换为 SQL LIMIT/ORDER 查询条件.
 *
 * @param int $limitDefault 默认记录数
 * @param array $sortableColumns sortby 对应的模块数据表中支持排序的属性
 * @return array
 */
function ApiGetRequestFilter($limitDefault = 10, $sortableColumns = array())
{
    $condition = array(
        'limit' => array(0, $limitDefault),
        'order' => null,
        'option' => null,
    );
    $sortBy = strtolower((string) GetVars('sortby'));
    $order = strtoupper((string) GetVars('order'));
    $limit = (int) GetVars('limit');
    $offset = (int) GetVars('offset');
    $pageNow = (int) GetVars('pagenow');
    $perPage = (int) GetVars('perpage');

    // 排序顺序
    if (!empty($sortBy) && isset($sortableColumns[$sortBy])) {
        $condition['order'] = array($sortableColumns[$sortBy] => 'ASC');
    }
    if (!is_null($condition['order']) && $order == 'DESC') {
        $condition['order'][$sortableColumns[$sortBy]] = $order;
    }

    if ($perPage) {
        $p = new Pagebar(null, false); // 第一个参数为 null，不需要分页 Url 处理
        $p->PageNow = (int) $pageNow == 0 ? 1 : (int) $pageNow;
        $p->PageCount = $perPage;
        $limit = array(($p->PageNow - 1) * $p->PageCount, $p->PageCount);
        $op = array('pagebar' => &$p);

        $condition['limit'] = $limit;
        $condition['option'] = $op;
    } else {
        if ($limit > 0) {
            $limitDefault = $limit;
            $condition['limit'][1] = $limit;
        }

        if ($offset > 0) {
            $condition['limit'][0] = $offset;
        }
    }

    foreach ($GLOBALS['hooks']['Filter_Plugin_API_Get_Request_Filter'] as $fpname => &$fpsignal) {
        $fpname($condition);
    }
    return $condition;
}

/**
 * 获取分页信息.
 *
 * @param PageBar|null $pagebar
 * @return array
 */
function ApiGetPaginationInfo($pagebar = null)
{
    if ($pagebar === null) {
        // 用 stdClass 而不用 array() ，为了为空时 json 显示 {} 而不是 []
        return new stdClass;
    }

    $info = array();
    $pagebar = &$pagebar['pagebar'];

    $info['Count'] = $pagebar->Count;
    $info['PageCount'] = $pagebar->PageBarCount;
    $info['PageAll'] = $pagebar->PageAll;
    $info['PageNow'] = $pagebar->PageNow;
    $info['PageFirst'] = $pagebar->PageFirst;
    $info['PageLast'] = $pagebar->PageLast;
    $info['PagePrevious'] = $pagebar->PagePrevious;
    $info['PageNext'] = $pagebar->PageNext;

    foreach ($GLOBALS['hooks']['Filter_Plugin_API_Get_Pagination_Info'] as $fpname => &$fpsignal) {
        $fpname($info, $pagebar);
    }
    return $info;
}

/**
 * API 获取及过滤关联对象请求.
 *
 * @param array $info 传入到 ApiGetObjectArray 的关联信息
 * @return array
 */
function ApiGetAndFilterRelationQuery($info)
{
    $relations_req = trim(GetVars('with_relations'));

    if (empty($relations_req)) {
        return array();
    }

    $relations = explode(',', $relations_req);
    $ret_relations = array();

    foreach ($relations as $relation) {
        $relation = trim($relation);
        if (array_key_exists($relation, $info)) {
            $ret_relations[$relation] = $info[$relation];
        }
    }

    return $ret_relations;
}

/**
 * API 传统登录时的 CSRF 验证.
 */
function ApiVerifyCSRF()
{
    global $zbp;

    if (! defined('ZBP_IN_API_VERIFYBYTOKEN')) {
        $csrftoken = GetVars('csrf_token');

        if (! $zbp->VerifyCSRFToken($csrftoken, 'api')) {
            ApiResponse(null, null, 419, $GLOBALS['lang']['error']['5']);
        }
    }
}

/**
 * API 载入 POST 数据（前端 JSON）.
 */
function ApiLoadPostData()
{
    $input = file_get_contents('php://input');
    if ($input && ($data = json_decode($input, true)) && is_array ($data)) {
        $_POST = array_merge ($data, $_POST);
    }
}

/**
 * API 派发.
 *
 * @param array       $mods
 * @param string      $mod
 * @param string|null $act
 */
function ApiDispatch($mods, $mod, $act)
{
    if (empty($act)) {
        $act = 'get';
    }

    if (isset($mods[$mod]) && file_exists($mod_file = $mods[$mod])) {
        include_once $mod_file;
        $func = 'api_' . $mod . '_' . $act;
        if (function_exists($func)) {
            $result = call_user_func($func);
    
            ApiResponse(
                isset($result['data']) ? $result['data'] : null,
                isset($result['error']) ? $result['error'] : null,
                isset($result['code']) ? $result['code'] : 200,
                isset($result['message']) ? $result['message'] : 'OK'
            );
        }
    }
    
    ApiResponse(null, null, 404, $GLOBALS['lang']['error']['96']);
}

function ApiThrottle($name = 'default', $max_reqs = 5, $period = 60)
{
    global $zbpcache;

    if (!isset($zbpcache)) {
        return;
    }

    $user_id = md5(GetGuestIP());

    $cache_key = "api-throttle:$name:$user_id";
    $cached_value = $zbpcache->Get($cache_key);
    $cached_req = json_decode($cached_value, true);
    if (!$cached_value || !$cached_req || (time() >= $cached_req['expire_time'])) {
        $cached_req = array('hits' => 0, 'expire_time' => (time() + $period));
    }

    if ($cached_req['hits'] >= $max_reqs) {
        ApiResponse(null, null, 429, 'Too many requests.');
    }

    $cached_req['hits']++;
    $zbpcache->Set($cache_key, json_encode($cached_req), $cached_req['expire_time'] - time());
}
