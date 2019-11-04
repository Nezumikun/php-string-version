<?php

namespace Nezumikun;

use Jfcherng\Diff\SequenceMatcher;


final class StringVersion {
  private $versions = [];
  private $resultString = null;
  private $ranges = [];
  private $tags = [ '', 'b', 'i', 'u' ];

  public function __construct(string $original) {
    $this->addVersion($original);
    $this->calc();
  }

  public function addVersion(string $version) {
    $this->versions[] = $version;
    $this->calc();
  }

  public function calc() {
    $this->resultString = $this->versions[0];
    $this->ranges = [];
    $this->ranges[] = [
      'from' => 0,
      'to' => \mb_strlen($this->resultString),
      'version' => 0,
    ];
    if (count($this->versions) < 2) {
      return;
    }
    for ($i = 1; $i < count($this->versions); $i++) {
      $old = preg_split('//u', $this->resultString, -1, PREG_SPLIT_NO_EMPTY);
      $new = preg_split('//u', $this->versions[$i], -1, PREG_SPLIT_NO_EMPTY);
      $matcher = new SequenceMatcher($old, $new);
      $resultString = '';
      $ranges = [];
      foreach ($matcher->getOpcodes() as $match) {
        $pos = \mb_strlen($resultString);
        if ($match[0] === SequenceMatcher::OP_EQ) { // без изменений
          $temp = \mb_substr($this->resultString, $match[1], $match[2] - $match[1]);
          $resultString .= $temp;
          foreach ($this->ranges as $range) {
            if ($range['to'] < $match[1]) {
              continue;
            }
            if ($range['from'] > $match[2]) {
              continue;
            }
            $ranges[] = [
              'from' => $pos + (($match[1] > $range['from']) ? $match[1] : $range['from']) - $match[1],
              'to' => $pos + (($match[2] < $range['to']) ? $match[2] : $range['to']) - $match[1],
              'version' => $range['version'],
            ];
          }
        }
        elseif (
          ($match[0] === SequenceMatcher::OP_REP) // замена
          || ($match[0] === SequenceMatcher::OP_INS) // вставка
        ) {
          $len = $match[4] - $match[3];
          $temp = \mb_substr($this->versions[$i], $match[3], $len);
          $resultString .= $temp;
          $ranges[] = [
            'from' => $pos,
            'to' => $pos + $len,
            'version' => $i,
          ];
        }
      }
      $this->resultString = $resultString;
      $this->ranges = $ranges;
    }
  }

  public function getResultAsHtml() {
    $tags = $this->tags;
    $temp = '';
    $prev = -1;
    for ($i = 0; $i < count($this->ranges); $i++) {
      if ($prev >= 0) {
        if ($tags[$prev] !== '') {
          $temp .= '</' . $tags[$prev] . '>';
        }
      }
      $prev = $this->ranges[$i]['version'];
      if ($tags[$prev] !== '') {
        $temp .= '<' . $tags[$prev] . '>';
      }
      $temp .= \mb_substr($this->resultString, $this->ranges[$i]['from'], $this->ranges[$i]['to'] - $this->ranges[$i]['from']);
    }
    if ($prev >= 0) {
      if ($tags[$prev] !== '') {
        $temp .= '</' . $tags[$prev] . '>';
      }
    }
    return $temp;
  }
}
