<?php
declare(strict_types = 1);

namespace Vecnavium\SkyBlocksPM\scorehud;

use Ifera\ScoreHud\event\TagsResolveEvent;
use pocketmine\event\Listener;
use function str_starts_with;

class ScoreHudListener implements Listener {
    private ScoreHudAddon $scorehudManager;
    
    /** @var array<string, callable(string): string|int> */
    private array $tagHandlers;

    public function __construct(ScoreHudAddon $scorehudManager) {
        $this->scorehudManager = $scorehudManager;
        
        $this->tagHandlers = [
            ScoreHudTags::ISLAND_NAME => [$this->scorehudManager, 'getIslandName'],
            ScoreHudTags::ISLAND_MEMBERS => [$this->scorehudManager, 'getOnlineMembers'],
            ScoreHudTags::PLAYER_RANK => [$this->scorehudManager, 'getPlayerRank']
        ];
    }

    /**
     * @param TagsResolveEvent $event
     * @phpstan-ignore-next-line
     */
    public function onTagResolve(TagsResolveEvent $event): void {
        $tag = $event->getTag();
        $tagName = $tag->getName();
        
        if (!str_starts_with($tagName, ScoreHudTags::PREFIX)) {
            return;
        }
        
        if (isset($this->tagHandlers[$tagName])) {
            $handler = $this->tagHandlers[$tagName];
            $tag->setValue((string) $handler($event->getPlayer()->getName()));
            return;
        }
        
        $tag->setValue(ScoreHudTags::NOT_AVAILABLE);
    }
}