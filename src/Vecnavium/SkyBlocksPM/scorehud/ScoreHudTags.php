<?php

declare(strict_types=1);

namespace Vecnavium\SkyBlocksPM\scorehud;

interface ScoreHudTags
{
    /** @var string */ 
    public const PREFIX = "skyblockspm.";
    
    /** @var string */ 
    public const ISLAND_NAME = self::PREFIX . "name";
    
    /** @var string */ 
    public const ISLAND_MEMBERS = self::PREFIX . "online.members";
    
    /** @var string */ 
    public const PLAYER_RANK = self::PREFIX . "rank";
    
    /** @var string */ 
    public const NOT_AVAILABLE = "N/A";
    
}

