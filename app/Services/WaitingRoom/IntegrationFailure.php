<?php
namespace App\Services\WaitingRoom;
final class IntegrationFailure extends \RuntimeException {
 public function __construct(public string $reason,public int $httpStatus=503){parent::__construct($reason);}
}
