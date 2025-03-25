<?php

declare(strict_types = 1);

namespace Vecnavium\SkyBlocksPM\scorehud;

use Ifera\ScoreHud\event\PlayerTagUpdateEvent;
use Ifera\ScoreHud\scoreboard\ScoreTag;
use pocketmine\player\Player as P;
use Vecnavium\SkyBlocksPM\player\Player;
use pocketmine\scheduler\ClosureTask;
use Vecnavium\SkyBlocksPM\skyblock\SkyBlock;
use Vecnavium\SkyBlocksPM\skyblock\SkyBlockRanks;
use Vecnavium\SkyBlocksPM\SkyBlocksPM;

/**
 * @phpstan-type PlayerCacheItem Player
 * @phpstan-type IslandCacheItem SkyBlock
 */
class ScoreHudAddon {
    protected SkyBlocksPM $plugin;
    
    /** @var array<string, PlayerCacheItem> */
    private array $playerCache = [];
    
    /** @var array<string, IslandCacheItem> */
    private array $islandCache = [];
    
    private const CACHE_TTL = 30;
    
    /** @var array<string, int> */
    private array $lastCacheUpdate = [];

    public function __construct(SkyBlocksPM $plugin) {
        $this->plugin = $plugin;
        $this->registerEvents();
        $this->onLoad();
    }

    public function registerEvents(): void {
        $this->plugin->getServer()->getPluginManager()->registerEvents(new ScoreHudListener($this), $this->plugin);
    }

    public function onLoad(): void {
        $this->repeat(function() {
            $currentPlayers = $this->plugin->getServer()->getOnlinePlayers();
            
            /** @var array<string, array<string, string|int>> */
            $updates = [];
            
            foreach ($currentPlayers as $player) {
                if (!$player->isOnline()) {
                    continue;
                }
                
                $playerName = $player->getName();
                $this->updateCache($playerName);
                
                $updates[$playerName] = [
                    ScoreHudTags::ISLAND_NAME => $this->getIslandName($playerName),
                    ScoreHudTags::ISLAND_MEMBERS => $this->getOnlineMembers($playerName),
                    ScoreHudTags::PLAYER_RANK => $this->getPlayerRank($playerName)
                ];
            }
            
            foreach ($updates as $playerName => $data) {
                $player = $this->plugin->getServer()->getPlayerExact($playerName);
                if ($player === null) continue;
                
                foreach ($data as $tag => $value) {
                    if (class_exists(PlayerTagUpdateEvent::class)) {
                        (new PlayerTagUpdateEvent($player, new ScoreTag($tag, (string)$value)))->call();
                    }
                }
            }
        }, $this->getUpdateDuration());
    }

    private function updateCache(string $playerName): void {
        $currentTime = time();
        
        if (isset($this->lastCacheUpdate[$playerName]) && 
            ($currentTime - $this->lastCacheUpdate[$playerName]) < self::CACHE_TTL) {
            return;
        }

        $player = $this->getSkyBlockPlayer($playerName);
        if ($player !== null) {
            $this->playerCache[$playerName] = $player;
            $island = $this->getSkyBlock($player);
            if ($island !== null) {
                $this->islandCache[$playerName] = $island;
            } else {
                unset($this->islandCache[$playerName]);
            }
        } else {
            unset($this->playerCache[$playerName]);
            unset($this->islandCache[$playerName]);
        }
        
        $this->lastCacheUpdate[$playerName] = $currentTime;
    }

    public function getPlayerRank(string $playerName): string {
        if (!isset($this->islandCache[$playerName])) {
            return ScoreHudTags::NOT_AVAILABLE;
        }

        $island = $this->islandCache[$playerName];
        $managers = $island->getManagers();
        
        if (in_array($playerName, $managers, true)) {
            return SkyBlockRanks::MANAGER;
        }
        
        return $island->getLeader() === $playerName ? SkyBlockRanks::LEADER : SkyBlockRanks::MEMBER;
    }

    public function getIslandName(string $playerName): string {
        if (!isset($this->islandCache[$playerName])) {
            return ScoreHudTags::NOT_AVAILABLE;
        }
        return $this->islandCache[$playerName]?->getName() ?? ScoreHudTags::NOT_AVAILABLE;
    }

    public function getOnlineMembers(string $playerName): int|string {
        if (!isset($this->islandCache[$playerName])) {
            return ScoreHudTags::NOT_AVAILABLE;
        }

        $island = $this->islandCache[$playerName];
        $members = $island->getMembers();
        $server = $this->plugin->getServer();
        
        return array_reduce($members, function($count, $memberName) use ($server) {
            $member = $server->getPlayerExact($memberName);
            return $count + ($member instanceof P && $member->isOnline() ? 1 : 0);
        }, 0);
    }

    public function getSkyBlock(Player $player): ?SkyBlock {
        return $this->plugin->getSkyBlockManager()->getSkyBlockByUuid($player->getSkyBlock());
    }

    public function getSkyBlockPlayer(string $playerName): ?Player {
        return $this->plugin->getPlayerManager()->getPlayer($playerName);
    }

    public function getUpdateDuration(): int {
        return $this->plugin->getConfig()->get("scorehud-tag-update-duration");
    }

    public function repeat(callable $callback, int $interval): void {
        $scheduler = $this->plugin->getScheduler();
        $scheduler->scheduleRepeatingTask(new ClosureTask($callback), $interval * 20);
    }
}