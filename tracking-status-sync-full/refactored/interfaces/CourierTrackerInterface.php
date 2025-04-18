<?php
namespace Interfaces;

interface CourierTrackerInterface {
    public function track(string $hawb): array;
}
