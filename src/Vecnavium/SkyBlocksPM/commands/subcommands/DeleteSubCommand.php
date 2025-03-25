<?php

declare(strict_types=1);

namespace Vecnavium\SkyBlocksPM\commands\subcommands;

use Vecnavium\SkyBlocksPM\libs\CortexPE\Commando\args\RawStringArgument;
use Vecnavium\SkyBlocksPM\libs\CortexPE\Commando\BaseSubCommand;
use pocketmine\command\CommandSender;
use pocketmine\player\Player as P;
use pocketmine\utils\Filesystem;
use pocketmine\utils\Utils;
use pocketmine\world\World;
use Vecnavium\SkyBlocksPM\player\Player;
use Vecnavium\SkyBlocksPM\skyblock\SkyBlock;
use Vecnavium\SkyBlocksPM\SkyBlocksPM;
use function strval;

class DeleteSubCommand extends BaseSubCommand {
    
    protected function prepare(): void {
        $this->setPermission('skyblockspm.delete');
        $this->registerArgument(0, new RawStringArgument('name'));
    }

    /**
     * @param CommandSender $sender
     * @param string $aliasUsed
     * @param array $args
     * @return void
     *
     * @phpstan-ignore-next-line
     */
    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        /** @var SkyBlocksPM $plugin */
        $plugin = $this->getOwningPlugin();
        
        $name = strval($args['name']);
        if (!$this->validatePermissions($sender, $name, $plugin)) {
            return;
        }

        $skyblockPlayer = $plugin->getPlayerManager()->getPlayer($name);
        if (!$this->validatePlayer($skyblockPlayer, $sender, $plugin)) {
            return;
        }

        $skyblock = $plugin->getSkyBlockManager()->getSkyBlockByUuid($skyblockPlayer->getSkyBlock());
        if(!$skyblock instanceof SkyBlock) return;

        $defaultWorld = $plugin->getServer()->getWorldManager()->getDefaultWorld();
        if(!$defaultWorld instanceof World) return;

        $this->handleMembers($skyblock, $defaultWorld, $plugin);
        $this->handleWorldDeletion($skyblock, $defaultWorld, $plugin);
        
        $plugin->getSkyBlockManager()->deleteSkyBlock($skyblock->getName());
        
        $sender->sendMessage($plugin->getMessages()->getMessage('deleted-sb', [
            '{NAME}' => $skyblockPlayer->getName()
        ]));
    }

    private function validatePermissions(CommandSender $sender, string $name, SkyBlocksPM $plugin): bool {
        if ($name !== $sender->getName() && !$sender->hasPermission('skyblockspm.deleteothers')) {
            $sender->sendMessage($plugin->getMessages()->getMessage('no-perms-delete'));
            return false;
        }
        return true;
    }

    private function validatePlayer(?Player $skyblockPlayer, CommandSender $sender, SkyBlocksPM $plugin): bool {
        if (!$skyblockPlayer instanceof Player) {
            $sender->sendMessage($plugin->getMessages()->getMessage('player-not-online'));
            return false;
        }
        if ($skyblockPlayer->getSkyBlock() == '') {
            $sender->sendMessage($plugin->getMessages()->getMessage('no-island'));
            return false;
        }
        return true;
    }

    private function handleMembers(SkyBlock $skyblock, World $defaultWorld, SkyBlocksPM $plugin): void {
        foreach ($skyblock->getMembers() as $member) {
            $this->teleportPlayer($member, $defaultWorld, $plugin);
            $this->handleMemberData($member, $plugin);
        }
    }

    private function handleWorldDeletion(SkyBlock $skyblock, World $defaultWorld, SkyBlocksPM $plugin): void {
        $world = $plugin->getServer()->getWorldManager()->getWorldByName($skyblock->getWorld());
        if(!$world instanceof World) return;

        foreach ($world->getPlayers() as $player) {
            $player->teleport($defaultWorld->getSpawnLocation());
        }

        if ($world->isLoaded()) {
            $folderName = $world->getFolderName();
            $plugin->getServer()->getWorldManager()->unloadWorld($world);
            Filesystem::recursiveUnlink($plugin->getServer()->getDataPath() . 'worlds' . DIRECTORY_SEPARATOR . $folderName);
        }
    }

    private function teleportPlayer(string $playerName, World $defaultWorld, SkyBlocksPM $plugin): void {
        $player = $plugin->getServer()->getPlayerExact($playerName);
        if ($player instanceof P) {
            $player->teleport($defaultWorld->getSpawnLocation());
        }
    }

    private function handleMemberData(string $member, SkyBlocksPM $plugin): void {
        if(($mPlayer = $plugin->getPlayerManager()->getPlayer($member)) instanceof Player) {
            $mPlayer->setSkyBlock('');
        } else {
            $plugin->getPlayerManager()->deleteSkyBlockOffline($member);
        }
    }
}
