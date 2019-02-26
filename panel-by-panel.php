<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);

class PanelByPanel
{
    private $page = 0;
    private $name = 'comic';
    private $acbf = '';

    public function __construct() {
        // Read config
        require_once('panel-by-panel.conf');
        $this->home = $home;
        $this->thumbMaxWidth = $thumbMaxWidth;
        $this->thumbMaxHeight = $thumbMaxHeight;
        
        // Get comic and page
        $this->name = 'comic';
        if (isset($_GET['comic'])) {
            $this->name = $_GET['comic'];
        }
        $this->lang = 'en';
        if (isset($_GET['lang'])) {
            $this->lang = $_GET['lang'];
        }
        if (!isset($_GET['page'])) {
            $this->page = 0;
        } else if (!ctype_digit($_GET['page'])) {
            $this->page = 0;
        } else {
            $this->page = (int)$_GET['page'];
        }
        
        // Read Advanced Comic Book Format
        $acbf_string = file_get_contents('./'.$this->name.'/'.$this->name.'.acbf');
        $this->acbf = new SimpleXMLElement($acbf_string);
    }

    public function get_home() {
        return $this->home;
    }

    public function get_image() {
        if ($this->page == 0) {
            $image = $this->name."/".$this->acbf->{'meta-data'}->{'book-info'}->coverpage->image['href'];
        } else {
            $image = $this->name."/".$this->acbf->body->page[$this->page-1]->image['href'];
        }
        return $image;
    }

    public function get_title() {
        return $this->in_lang($this->acbf->{'meta-data'}->{'book-info'}->{'book-title'});
    }

    public function get_page_of() {
        return $this->page." of ".sizeof($this->acbf->body->page);
    }

    public function get_authors() {
        $retstring = '<ul class="credits">';
        foreach ($this->acbf->{'meta-data'}->{'book-info'}->{'author'} as $author) {
            $retstring .= '<li>';
            $attr = $author->attributes();
            if (isset($attr['activity'])) {
                $retstring .= $attr['activity'] .' - ';
            }
            $retstring .= $author->{'first-name'};
            if (isset($author->{'middle-name'})) {
                $retstring .= ' '.$author->{'middle-name'};
            }
            $retstring .= ' '.$author->{'last-name'};
            if (isset($author->{'nickname'})) {
                $retstring .= ' ('.$author->{'nickname'}.')';
            }
            $retstring .= '<br/>';
            $retstring .= '</li>';
        }
        return $retstring.'</ul>';
    }

    public function get_summary() {
        $summary = $this->in_lang($this->acbf->{'meta-data'}->{'book-info'}->{'annotation'});
        $retstring = '';
        foreach ($summary->p as $paragraph) {
            $retstring .= '<p>'.$paragraph.'</p>';
        }
        return $retstring;
    }

    public function get_bgcolor() {
        if ($this->page == 0) {
            if (isset($this->acbf->{'meta-data'}->{'book-info'}->coverpage['bgcolor'])) {
                $bgcolor = $this->acbf->{'meta-data'}->{'book-info'}->coverpage['bgcolor'];
            } else if (isset($this->acbf->body['bgcolor'])) {
                $bgcolor = $this->acbf->body['bgcolor'];
            }
        } else {
            if (isset($this->acbf->body->page[$this->page-1]['bgcolor'])) {
                $bgcolor = $this->acbf->body->page[$this->page-1]['bgcolor'];
            } else if (isset($this->acbf->body['bgcolor'])) {
                $bgcolor = $this->acbf->body['bgcolor'];
            }        
        }
        return $bgcolor;
    }

    public function get_next() {
        if ($this->page >= sizeof($this->acbf->body->page)) {
            $next_page = "TODO! Exit";
        } else {
            $next_page = "index.php?comic=".$this->name."&page=" . ($this->page + 1);
        }
        return $next_page;
    }
    
    public function get_prev() {
        if ($this->page <= 0) {
            $prev_page = "TODO! Home";
        } else {
            $prev_page = "index.php?comic=".$this->name."&page=" . ($this->page - 1);
        }
        return $prev_page;
    }

    public function get_thumbs() {
        return "thumbs.php?comic=".$this->name."&page=".($this->page);
    }

    public function get_thumbs_bgcolor() {
        return $this->acbf->body['bgcolor'];
    }


    public function draw_thumbs() {
        // Create thumbs folder, if not there
        $cwd = getcwd();
        $thumbsdir = $cwd."/".$this->name."/thumbs";
        if (!file_exists($thumbsdir)) {
            mkdir($thumbsdir, 0777, true);
        }

        // Generate HTML
        $html = "";
        for ($i = 0; $i < sizeof($this->acbf->body->page);$i++) {
            if ($i == 0) {
                $image = $this->name."/".$this->acbf->{'meta-data'}->{'book-info'}->coverpage->image['href'];
                $thumb = $this->name."/thumbs/".$this->acbf->{'meta-data'}->{'book-info'}->coverpage->image['href'];
            } else {
                $image = $this->name."/".$this->acbf->body->page[$i-1]->image['href'];
                $thumb = $this->name."/thumbs/".$this->acbf->body->page[$i-1]->image['href'];
            }
            if ($i == $this->page) {
                $id = "active-thumb";
            } else {
                $id = "page-".$i;
            }
            $this->check_thumb($cwd."/".$image, $cwd."/".$thumb);
            $html .= "\t<a class='thumblink' href='index.php?comic=".$this->name."&page=".$i."'>\n";
            $html .= "\t\t<img class='thumb' id='".$id."' src=".$thumb." />\n";
            $html .= "\t</a>\n";
        }
        return $html;
    }

    private function in_lang($data) {
        if (sizeof($data) == 1) {
            return $data[0];
        }
        foreach ($data as $d) {
            $attr = $d->attributes();
            if (isset($attr['lang'])) {
                if ($attr['lang'] == $this->lang) {
                    return $d;
                }
            }
        }
        return $data[0];
    }

    private function check_thumb($image, $thumb) {
        if (!file_exists($thumb)) {
            $this->make_thumb($image, $thumb);
        } elseif (filemtime($thumb) < filemtime($image)) {
            $this->make_thumb($image, $thumb);
        }
    }

    private function make_thumb($image, $thumb) {
        $im = new Imagick();
        $im->readImage($image);
        $im->thumbnailImage($this->thumbMaxWidth, $this->thumbMaxHeight, true);
        $im->writeImage($thumb);
        $im->destroy();
    }
}

$pbp = new PanelByPanel();

?>
