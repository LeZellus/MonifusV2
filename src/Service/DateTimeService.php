<?php

namespace App\Service;

class DateTimeService
{
    private static ?\DateTimeImmutable $cachedNow = null;
    private static ?string $cachedDay = null;

    public function now(): \DateTimeImmutable
    {
        $currentDay = date('Y-m-d');
        
        // Cache the DateTime object for the current day to avoid multiple instantiations
        if (self::$cachedNow === null || self::$cachedDay !== $currentDay) {
            self::$cachedNow = new \DateTimeImmutable();
            self::$cachedDay = $currentDay;
        }
        
        return self::$cachedNow;
    }

    public function createDateTime(string $time = 'now'): \DateTime
    {
        return new \DateTime($time);
    }

    public function createDateTimeImmutable(string $time = 'now'): \DateTimeImmutable
    {
        return new \DateTimeImmutable($time);
    }

    public function weekAgo(): \DateTime
    {
        return new \DateTime('-7 days');
    }

    public function monthAgo(): \DateTime
    {
        return new \DateTime('-30 days');
    }

    public function yesterday(): \DateTime
    {
        return new \DateTime('-24 hours');
    }

    public function twoWeeksAgo(): \DateTime
    {
        return new \DateTime('-14 days');
    }

    public function daysAgo(int $days): \DateTime
    {
        return new \DateTime("-{$days} days");
    }
}