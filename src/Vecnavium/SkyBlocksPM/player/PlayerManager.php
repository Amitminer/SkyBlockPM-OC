<?php

declare(strict_types = 1);

namespace Vecnavium\SkyBlocksPM\player;

use pocketmine\player\Player as P;
use Vecnavium\SkyBlocksPM\SkyBlocksPM;

class PlayerManager {

    /** @var Player[] */
    private array $players = [];

    /** @var array<string, bool> */
    public array $coopPlayers = [];

    public function __construct(private SkyBlocksPM $plugin) {}

    public function loadPlayer(P $player): void {
        $this->plugin->getDataBase()->executeSelect(
            'skyblockspm.player.load',
            [
                'uuid' => $player->getUniqueId()->toString()
            ],
            function (array $rows) use ($player): void {
                if (count($rows) == 0) {
                    $this->createPlayer($player);
                    return;
                }
                $name = $player->getName();
                $this->players[$name] = new Player($rows[0]['uuid'], $rows[0]['name'], $rows[0]['skyblock']);
                if ($name !== $rows[0]['name']) {
                    $this->getPlayer($name)?->setName($name);
                }
                $this->plugin->getSkyBlockManager()->loadSkyblock($rows[0]['skyblock']);
            }
        );
    }

    public function unloadPlayer(P $player): void {
        $skyBlockPlayer = $this->getPlayer($player->getName());

        if ($skyBlockPlayer instanceof Player) {
            $this->plugin->getSkyBlockManager()->unloadSkyBlock($skyBlockPlayer->getSkyBlock());
        }

        if (isset($this->players[$player->getName()])) {
            unset($this->players[$player->getName()]);
        }
    }

    public function createPlayer(P $player): void {
        $this->plugin->getDataBase()->executeInsert('skyblockspm.player.create',
            [
                'uuid' => $player->getUniqueId()->toString(),
                'name' => $player->getName(),
                'skyblock' => ''
            ]);
        $this->players[$player->getName()] = new Player($player->getUniqueId()->toString(), $player->getName(), '');
    }

    /**
    * @param string $name
    * @return Player|null
    * @phpstan-return Player|null
    */
    public function getPlayer(string $name): ?Player {
        return $this->players[$name] ?? null;
    }

    /**
    * Checks if a player is a cooperator.
    *
    * @param string $playerName The name of the player to check.
    * @return bool True if the player is a cooperator, false otherwise.
    */
    public function isPlayerCooperator(string $playerName): bool {
        return isset($this->coopPlayers[$playerName]);
    }

    /**
    * Removes a cooperator from the list.
    *
    * @param string $playerName The name of the player to remove as a cooperator.
    * @return void
    */
    public function removeCooperator(string $playerName): void {
        if ($this->isPlayerCooperator($playerName)) {
            unset($this->coopPlayers[$playerName]);
        }
    }

    /**
    * Adds a cooperator to the list.
    *
    * @param string $playerName The name of the player to add as a cooperator.
    * @return void
    */
    public function addCooperator(string $playerName): void {
        if (!$this->isPlayerCooperator($playerName)) {
            $this->coopPlayers[$playerName] = true;
        }
    }

    /**
    * This is used for Skyblock members that are offline when the Skyblock is deleted by the leader.
    *
    * @param string $name
    * @param string $skyblock
    * @return void
    */
    public function deleteSkyBlockOffline(string $name, string $skyblock = ''): void {
        $this->plugin->getDataBase()->executeGeneric(
            'skyblockspm.sb.delete_offline', [
                'name' => $name,
                'skyblock' => $skyblock
            ]
        );
    }
}