<?php

class ePuStaLogline {
    public $uuid;
    public $errors;
    public $sessionId;
    public $documentIdentifier;
    public $associatedIdentifier;
    public $tags;
    public $rawLogline;
    public $time;

    function __construct() {
    }
}

class ePuStaLoglineParser {
    private $regExp;
    private $timeRegExp;

    function __construct() {
        // New ePuSta format: UUID [Errors] SessionID [DocumentIdentifier] [AssociatedIdentifier] [Tags] RawLogline
        $this->regExp = '/^([^ ]+) (\[[^\]]*\]) ([^ ]*) (\[[^\]]*\]) (\[[^\]]*\]) (\[[^\]]*\]) (.*)/';
        // Extract time from Apache log format: [dd/Mon/YYYY:HH:mm:ss +ZZZZ]
        $this->timeRegExp = '/\[([^\]]+)\]/';
    }

    public function parse($line, &$logline) {
        $logline = new ePuStaLogline();
        if (!preg_match($this->regExp, $line, $match)) {
            fwrite(STDERR, "Error: can't parse ePuSta logline:\n");
            fwrite(STDERR, "    " . $line . "\n");
            return false;
        }

        $logline->uuid = trim($match[1]);
        $logline->errors = json_decode(trim($match[2]), true) ?? [];
        $logline->sessionId = trim($match[3]);
        $logline->documentIdentifier = json_decode(trim($match[4]), true) ?? [];
        $logline->associatedIdentifier = json_decode(trim($match[5]), true) ?? [];
        $logline->tags = json_decode(trim($match[6]), true) ?? [];
        $logline->rawLogline = trim($match[7]);

        // Extract time from rawLogline (Apache log format: [dd/Mon/YYYY:HH:mm:ss +ZZZZ])
        if (preg_match($this->timeRegExp, $logline->rawLogline, $timeMatch)) {
            $logline->time = $timeMatch[1];
        }

        return true;
    }
}

?>
