<?php
/**
 * DokuWiki Plugin do (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 * @author  Adrian Lang <lang@cosmocode.de>
 * @author  Dominik Eckelmann <eckelmann@cosmocode.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class syntax_plugin_do_dolist extends DokuWiki_Syntax_Plugin {

    public function getType() {
        return 'substition';
    }

    public function getPType() {
        return 'block';
    }

    public function getSort() {
        return 155;
    }

    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('{{dolist>.*?}}', $mode, 'plugin_do_dolist');
    }

    /**
     * Handler to prepare matched data for the rendering process
     *
     * @param   string        $match   The text matched by the patterns
     * @param   int           $state   The lexer state for the match
     * @param   int           $pos     The character position of the matched text
     * @param   Doku_Handler  $handler Reference to the Doku_Handler object
     * @return  array Return an array with all data you want to use in render()
     */
    public function handle($match, $state, $pos, Doku_Handler $handler) {
        // parse the given match
        $match = substr($match, 9, -2);

        // usability enhance
        //
        // if there is no ? but a & the user has probably forgot the ? befor the first arg.
        // in this case we'll replace the first & to a ?
        if(strpos($match, '?') === false) {
            $pos = strpos($match, '&');
            if(is_int($pos)) {
                $match[$pos] = '?';
            }
        }

        $url = explode('?', $match, 2);
        $ns = $url[0];
        $args = array();

        // check the arguments
        if(isset($url[1])) {
            parse_str($url[1], $args);

            // check for filters
            $filters = array_keys($args);
            foreach($filters as $filter) {
                if(isset($args[$filter])) {
                    $args[$filter] = explode(',', $args[$filter]);
                    $c = count($args[$filter]);
                    for($i = 0; $i < $c; $i++) {
                        $args[$filter][$i] = trim($args[$filter][$i]);
                    }
                }
            }
        }

        $args['ns'] = $ns;

        return $args;
    }

    /**
     * Create output
     *
     * @param string         $mode output format being rendered
     * @param Doku_Renderer  $R    reference to the current renderer object
     * @param array          $data data created by handler()
     * @return bool
     */
    public function render($mode, Doku_Renderer $R, $data) {
        if($mode != 'xhtml') return false;
        $R->info['cache'] = false;

        /** @var helper_plugin_do $hlp */
        $hlp = plugin_load('helper', 'do');
        $tasks = $hlp->loadTasks($data);

        if(count($tasks) === 0) {
            $R->tablerow_open();
            $R->tablecell_open(6, 'center');
            $R->cdata($this->getLang('none'));
            $R->tablecell_close();
            $R->tablerow_close();
            $R->doc .= '</table>';
            return true;
        }

        $R->doc .= $this->buildTasklistHTML($tasks, isset($data['user']), isset($data['creator']));

        return true;
    }

    /**
     * @param array $tasks
     * @param bool  $hideUser
     * @param bool  $hideCreator
     *
     * @return string
     */
    public function buildTasklistHTML (array $tasks, $hideUser, $hideCreator) {
        $userstyle = $hideUser ? ' style="display: none;"' : '';
        $creatorstyle = $hideCreator ? ' style="display: none;"' : '';

        $table = '<table class="inline plugin_do">';
        $table .= '    <tr>';
        $table .= '    <th>' . $this->getLang('task') . '</th>';
        $table .= '    <th' . $userstyle . '>' . $this->getLang('user') . '</th>';
        $table .= '    <th>' . $this->getLang('date') . '</th>';
        $table .= '    <th>' . $this->getLang('status') . '</th>';
        $table .= '    <th' . $creatorstyle . '>' . $this->getLang('creator') . '</th>';
        $table .= '    <th>' . $this->lang['js']['popup_msg'] . '</th>';
        $table .= '  </tr>';

        global $ID;
        /** @var helper_plugin_do $hlp */
        $hlp = plugin_load('helper', 'do');

        foreach($tasks as $row) {
            $table .= '<tr>';
            $table .= '<td class="plugin_do_page">';
            $table .= '<a title="' . $row['page'] . '" href="' . wl($row['page']) . '#plgdo__' . $row['md5'] . '" class="wikilink1">' . hsc($row['text']) . '</a>';
            $table .= '</td>';
            $table .= '<td class="plugin_do_assignee"' . $userstyle . '>';
            foreach($row['users'] as &$user) {
                $user = $hlp->getPrettyUser($user);
            }
            unset($user);
            $table .= implode(', ', $row['users']);
            $table .= '</td>';
            $table .= '<td class="plugin_do_date">' . hsc($row['date']) . '</td>';
            $table .= '<td class="plugin_do_status" align="center">';

            // task status icon...
            list($class, $image, $title) = $data = $this->prepareTaskInfo($row['user'], $row['date'], $row['status'], $row['closedby']);
            $editor = ($row['closedby']) ? $hlp->getPrettyUser($row['closedby']) : '';

            $table .= '<span class="plugin_do_item plugin_do_' . $row['md5'] . ($row['status'] ? ' plugin_do_done' : '') . '">'; // outer span

            // img link
            $table .= '<a href="' . wl($ID, array('do' => 'plugin_do', 'do_page' => $row['page'], 'do_md5' => $row['md5']));
            $table .= '" class="plugin_do_status">';

            $table .= '<img src="' . DOKU_BASE . 'lib/plugins/do/pix/' . $image . '" />';
            $table .= '</a>';
            $table .= '<span>' . $editor . '</span>';

            $table .= '</span>'; // outer span end

            $table .= '</td>';
            $table .= '<td class="plugin_do_creator"' . $creatorstyle . '>';
            $table .= $hlp->getPrettyUser($row['creator']);
            $table .= '</td>';
            $table .= '<td class="plugin_do_commit">';
            $table .= hsc($row['msg']);
            $table .= '</td>';

            $table .= '  </tr>';
        }

        $table .= '</table>';
        return $table;
    }

    /**
     * Returns array with some task info
     *
     * @param string $user user id
     * @param string $date due date
     * @param string $status null or closing date
     * @param string $closedBy user id
     * @return array with class, image name and title
     */
    protected function prepareTaskInfo($user, $date, $status, $closedBy) {
        $result = array();
        if($user && $date) {
            $result[] = 'plugin_do1';
        } elseif($user) {
            $result[] = 'plugin_do2';
        } elseif($date) {
            $result[] = 'plugin_do3';
        } else {
            $result[] = 'plugin_do4';
        }

        // change to "done" images
        $img = ($status) ? 'done.png' : 'undone.png';

        // setup title
        $title = '';

        if($user) {
            $title .= $this->getJsText('assignee', $user);
        }

        if($date) {
            $title .= $this->getJsText('due', $date);
        }

        if($status) {
            $title .= $this->getJsText('done', $status);
        }

        if($closedBy) {
            $title .= $this->getJsText('closedby', $closedBy);
        }

        $result[] = $img;
        $result[] = trim($title);

        return $result;
    }

    /**
     * Returns localized string from js strings and performs placeholder replacement
     *
     * @param string $str key of localized string
     * @param string $arg placeholder value
     * @return string
     */
    protected function getJsText($str, $arg) {
        return sprintf($this->lang['js'][$str], $arg) . ' ';
    }
}

