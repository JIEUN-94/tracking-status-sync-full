<?php
namespace App\Interfaces;

interface CourierTrackerInterface {
    public function track(string $hawb): array;
}
