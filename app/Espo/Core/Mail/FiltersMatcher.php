<?php

namespace Espo\Core\Mail;


use \Espo\Entities\Email;

class FiltersMatcher
{
    public function __construct()
    {

    }

    protected function matchTo(Email $email, $filter)
    {
        if ($email->get('to')) {
            $toArr = explode(';', $email->get('to'));
            foreach ($toArr as $to) {
                if ($this->matchString(strtolower($filter->get('to')), strtolower($to))) {
                    return true;
                }
            }
        }
    }

    public function match(Email $email, $subject, $skipBody = false)
    {
        if (is_array($subject) || $subject instanceof \Traversable) {
            $filterList = $subject;
        } else {
            $filterList = [$subject];
        }

        foreach ($filterList as $filter) {
            $filterCount = 0;

            if ($filter->get('from')) {
                $filterCount++;
                if (!$this->matchString(strtolower($filter->get('from')), strtolower($email->get('from')))) {
                    continue;
                }
            }

            if ($filter->get('to')) {
                $filterCount++;
                if (!$this->matchTo($email, $filter)) {
                    continue;
                }
            }

            if ($filter->get('subject')) {
                $filterCount++;
                if (!$this->matchString($filter->get('subject'), $email->get('name'))) {
                    continue;
                }
            }

            $wordList = $filter->get('bodyContains');
            if (!empty($wordList)) {
                $filterCount++;
                if ($skipBody) {
                    continue;
                }
                if (!$this->matchBody($email, $filter)) {
                    continue;
                }

            }

            if ($filterCount) {
                return true;
            }
        }

        return false;
    }

    protected function matchBody(Email $email, $filter)
    {
        $phraseList = $filter->get('bodyContains');
        $body = $email->get('body');
        $bodyPlain = $email->get('bodyPlain');
        foreach ($phraseList as $phrase) {
            if (stripos($bodyPlain, $phrase) !== false) {
                return true;
            }
            if (stripos($body, $phrase) !== false) {
                return true;
            }
        }
    }

    protected function matchString($pattern, $value)
    {
        if ($pattern == $value) {
            return true;
        }
        $pattern = preg_quote($pattern, '#');
        $pattern = str_replace('\*', '.*', $pattern).'\z';
        if (preg_match('#^'.$pattern.'#', $value)) {
            return true;
        }
        return false;
    }
}
