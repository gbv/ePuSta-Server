<?php

class ApacheLoglinePaser {

  public $RegExp;  

  function __construct() {
    $RegExp='(unknown|-|\d+\.\d+\.\d+\.\d+(, unknown)?|([A-Fa-f0-9]{1,4}:){7}[A-Fa-f0-9]{1,4}) ';      // IP Adress
    $RegExp.='(.*) ';                            // Remote logname
    $RegExp.='.* ';                              // Remote user
    $RegExp.='\[(.*)\] ';                        // Time the request was received
    $RegExp.='"(.*) (.*) (HTTP\/[1,2]\.[0,1])" ';  // http Method, request URL,
    $RegExp.='(\d\d\d) ';                        // http Status Code
    $RegExp.='[0-9-]+ ';                         // Size of response in bytes
    $RegExp.='"(.*)" ';                          // Referer
    $RegExp.='"(.*)"';                           // User Agent
  }

  public function parse($line,& $logline) {
    $RegExp2= '/^'.$RegExp.'/';
    if (! $logline)  {
      $logline=new Logline();
      echo "Error keine  Logline\n";
    }
    if (preg_match($RegExp2, $line, $treffer)) {
      
      $logline->IP=trim($treffer[1]);
      return true;
    } else {
      return false;
    }
  }
}

class Logline {
    public $IP;
    public $RemoteLogname;
    public $RemoteUser;
    public $Time;
    public $HttpMethod;
    public $URL;
    public $HttpProtokol;
    public $HttpStatusCode;
    public $SizeOfResponse;
    public $Referer;
    public $UserAgent;
    function __construct() {
    }

    public function __toString() {
        $str=$this->IP." ";
        $str.=$this->RemoteLogname." ";
        $str.=$this->RemoteUser." ";
        $str.='['.$this->Time."] ";
        $str.='"'.$this->HttpMethod." ";
        $str.=$this->URL." ";
        $str.=$this->HttpProtokol.'" ';
        $str.=$this->HttpStatusCode.' ';
        $str.=$this->SizeOfResponse." ";
        $str.='"'.$this->Referer.'" ';
        $str.='"'.$this->UserAgent.'"';

        return $str;
    }
}

class ReposasLogline extends Logline{
    public $Identifier;
    public $UUID;
    public $SessionID;
    public $Subjects;
  
    function __construct() {
    }

    public function __toString() {
        $str=$this->UUID." ";
        $str.=parent::__toString();
        $str.=" ";
        $str.=$this->SessionID." ";
        $str.=json_encode($this->Identifier)." ";
        $str.=json_encode($this->Subjects)." ";

        return $str;
    }
}

class ReposasLogfileParser {
    public $RegExp;
    //private $SubLoglineparser;

    function __construct() {
        //$SubLoglineparser=$subLoglineParser;
    
        $this->RegExp='([^ ]*) ';
        //$this->RegExp.='(unknown|-|\d+\.\d+\.\d+\.\d+(, unknown)?|([A-Fa-f0-9]{1,4}:){7}[A-Fa-f0-9]{1,4}) ';      // IP Adress
        $this->RegExp.='([^ ]*) ';                                 // IP Adress
        $this->RegExp.='([^ ]*) ';                                 // Remote logname
        $this->RegExp.='([^ ]*) ';                                 // Remote user
        $this->RegExp.='\[([^\]]*)\] ';                            // Time the request was received
        $this->RegExp.='"([^ ]*) ([^ ]*) (HTTP\/[1,2]\.[0,1])" ';  // http Method, request URL, http Protokoll
        $this->RegExp.='(\d\d\d) ';                                // http Status Code
        $this->RegExp.='([0-9-]+) ';                               // Size of response in bytes
        $this->RegExp.='"([^"]*)" ';                               // Referer
        $this->RegExp.='"([^"]*)"';                             // User Agent
        $this->RegExp.=' ';
        $this->RegExp.='(.*) ';                                    // SessionID
        $this->RegExp.='(\[[^\]]*\]) ';                            // Identifier
        $this->RegExp.='(\[[^\]]*\])';                             // Subjects
    }

    public function parse($line,& $logline) {
        $logline=new ReposasLogline;
        $RegExp2= '/^'.$this->RegExp.'/';
        if (! $logline)  {
            $logline=new Logline();
            echo "Error keine  Logline\n";
        }
        $line2=$line;
        $line2=str_replace('"\"','"',$line2);
        $line2=str_replace('\""','"',$line2);
        if (preg_match($RegExp2, $line2, $treffer)) {
            $logline->UUID=trim($treffer[1]);
            $logline->IP=trim($treffer[2]);
            $logline->RemoteLogname=trim($treffer[3]);
            $logline->RemoteUser=trim($treffer[4]);
            $logline->Time=trim($treffer[5]);
            $logline->HttpMethod=trim($treffer[6]);
            $logline->URL=trim($treffer[7]);
            $logline->HttpProtokol=trim($treffer[8]);
            $logline->HttpStatusCode=trim($treffer[9]);
            $logline->SizeOfResponse=trim($treffer[10]);
            $logline->Referer=trim($treffer[11]);
            $logline->UserAgent=trim($treffer[12]);
            $logline->SessionID=trim($treffer[13]);
            $logline->Identifier=json_decode ( trim($treffer[14]), true);
            $logline->Subjects=json_decode ( trim($treffer[15]), true);
            return true;
        } else {
            fwrite(STDERR, "Error: can't parse Logline:\n");
            fwrite(STDERR, "    ".$line2."\n");
            fwrite(STDERR, "    ".$RegExp2."\n");
            return false;
        }  
    }
    
}

?>
