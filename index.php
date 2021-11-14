<?php

class Cell
{
    private WeakMap $neighbors;

    public function __construct(private int $x, private int $y, private bool $isAlive)
    {
        $this->neighbors = new WeakMap();
    }

    public function x(): int
    {
        return $this->x;
    }

    public function y(): int
    {
        return $this->y;
    }

    public function alive(): void
    {
        $this->isAlive = true;
    }

    public function dead(): void
    {
        $this->isAlive = false;
    }

    public function status(): string
    {
        return $this->isAlive ? 'alive' : 'dead';
    }

    public function addNeighbor(Cell $cell): void
    {
        if ($cell->x() < $this->x - 1
            || $cell->x() > $this->x + 1
            || $cell->y() < $this->y - 1
            || $cell->y() > $this->y + 1
        ) {
            throw new Exception('This cell is not a neighbor');
        }

        $this->neighbors[$cell] = $cell;
    }

    /**
     * @return WeakMap|Cell[]
     */
    public function getNeighbors(): WeakMap
    {
        return $this->neighbors;
    }

    public function isAlive(): bool
    {
        return $this->isAlive;
    }

    public function aliveNeighborsCount(): int
    {
        $aliveCount = 0;
        foreach ($this->neighbors as $neighbor) {
            if ($neighbor->isAlive()) {
                $aliveCount++;
            }
        }

        return $aliveCount;
    }
}

class CellRepository
{
    private array $cells = [];

    public function find(int $x, int $y): ?Cell
    {
        return $this->cells[$x][$y] ?? null;
    }

    public function add(Cell $cell): void
    {
        $this->cells[$cell->x()][$cell->y()] = $cell;
    }

    public function updateCell(int $x, int $y, $isAlive): void
    {
        $cell = $this->find($x, $y);

        if (null !== $cell) {
            $isAlive ? $cell->alive() : $cell->dead();
        }
    }

    /**
     * Get cells list
     *
     * @return Cell[]|Iterator
     */
    public function getAll(): Iterator
    {
        foreach ($this->cells as $cells) {
            foreach ($cells as $cell) {
                yield $cell;
            }
        }
    }

    public function getAliveCount(): int
    {
        $count = 0;

        foreach ($this->getAll() as $cell) {
            if ($cell->isAlive()) {
                $count++;
            }
        }

        return $count;
    }

    public function cleanupOrphans(): void
    {
        foreach ($this->cells as $x => $cells) {
            foreach ($cells as $y => $cell) {
                if ($cell->aliveNeighborsCount() === 0) {
                    unset($this->cells[$x][$y], $cell);
                }
            }
        }
    }
}

class CellFactory
{
    public function __construct(private CellRepository $cellRepository)
    {
    }

    /**
     * Create cell with neighbors
     *
     * @param int $x
     * @param int $y
     * @param bool $isAlive
     * @return Cell
     */
    public function create(int $x, int $y, bool $isAlive): Cell
    {
        $cell = $this->cellRepository->find($x, $y);

        if (null === $cell) {
            // create base cell
            $cell = new Cell($x, $y, $isAlive);
            $this->cellRepository->add($cell);
        }

        // add neighbors
        // left top
        $this->addNeighbor($cell, $x - 1, $y + 1);
        // central top
        $this->addNeighbor($cell, $x, $y + 1);
        // right top
        $this->addNeighbor($cell, $x + 1, $y + 1);
        // right middle
        $this->addNeighbor($cell, $x + 1, $y);
        // right bottom
        $this->addNeighbor($cell, $x + 1, $y - 1);
        // central bottom
        $this->addNeighbor($cell, $x, $y - 1);
        // left bottom
        $this->addNeighbor($cell, $x - 1, $y - 1);
        // left middle
        $this->addNeighbor($cell, $x - 1, $y);

        $isAlive ? $cell->alive() : $cell->dead();

        return $cell;
    }

    private function addNeighbor(Cell $baseCell, int $x, int $y): void
    {
        $neighborCell = $this->cellRepository->find($x, $y);

        if (null === $neighborCell) {
            $neighborCell = new Cell($x, $y, false);

            $this->cellRepository->add($neighborCell);
        }

        $neighborCell->addNeighbor($baseCell);
        $baseCell->addNeighbor($neighborCell);
    }
}

class GameOfLife
{
    private int $currentGen = 1;

    public function __construct(private CellFactory $cellFactory, private CellRepository $cellRepository)
    {
    }

    /**
     * Init game with random cells
     *
     * @param int $count Alive cells count
     * @param int $areaWith Width of area to place alive cells
     * @param int $areaHeight Height of area to place alive cells
     * @throws Exception
     */
    public function initWithRandomPattern(int $count, int $areaWith = 10, int $areaHeight = 10): void
    {
        if ($count > $areaHeight * $areaWith) {
            throw new Exception('Area size is too small for '.$count.' points');
        }

        $points = [];
        $i = 0;

        while ($i < $count) {
            $x = \random_int(1, $areaWith);
            $y = \random_int(1, $areaHeight);
            if (isset($points[$x][$y])) {
                continue;
            }
            $points[$x][$y] = true;
            $i++;
        }

        foreach ($points as $x => $columns) {
            foreach ($columns as $y => $val) {
                $this->cellFactory->create($x, $y, $val);
            }
        }
    }

    /**
     * Init game with `Glider` pattern
     */
    public function initWithGliderPattern(): void
    {
        $this->cellFactory->create(0, 0, true);
        $this->cellFactory->create(1, -1, true);
        $this->cellFactory->create(1, -2, true);
        $this->cellFactory->create(0, -2, true);
        $this->cellFactory->create(-1, -2, true);
    }

    /**
     * Init game with 3-cells line pattern
     */
    public function initWith3CellLinePattern(): void
    {
        // 3 cells line pattern
        $this->cellFactory->create(-1, 0, true);
        $this->cellFactory->create(0, 0, true);
        $this->cellFactory->create(1, 0, true);
    }

    /**
     * Perform next gen operation
     */
    public function nextGen(): void
    {
        $this->currentGen++;
        $data = [];
        foreach ($this->cellRepository->getAll() as $cell) {
            $data[$cell->x()][$cell->y()] = [
                'is_alive' => $cell->isAlive(),
                'count' => $cell->aliveNeighborsCount(),
            ];
        }

        foreach ($data as $x => $rows) {
            foreach ($rows as $y => $columnData) {
                if ($columnData['is_alive']) {
                    if ($columnData['count'] < 2 || $columnData['count'] > 3) {
                        $this->cellFactory->create($x, $y, false);
                    }
                } elseif (!$columnData['is_alive'] && 3 === $columnData['count']) {
                    $this->cellFactory->create($x, $y, true);
                }
            }
        }

        $this->cellRepository->cleanupOrphans();
    }

    public function isGenerationAlive(): bool
    {
        return $this->cellRepository->getAliveCount() > 0;
    }

    public function currentGeneration(): int
    {
        return $this->currentGen;
    }

    public function printCurrentGen(int $width = 25, int $height = 25): void
    {
        echo 'Generation No: '.$this->currentGen.PHP_EOL;

        $preparedData = [];

        $minX = PHP_INT_MAX;
        $maxX = PHP_INT_MIN;
        $minY = PHP_INT_MAX;
        $maxY = PHP_INT_MIN;

        foreach ($this->cellRepository->getAll() as $cell) {
            if ($cell->isAlive()) {
                if ($cell->x() < $minX) {
                    $minX = $cell->x();
                }
                if ($cell->x() > $maxX) {
                    $maxX = $cell->x();
                }
                if ($cell->y() < $minY) {
                    $minY = $cell->y();
                }
                if ($cell->y() > $maxY) {
                    $maxY = $cell->y();
                }

                $preparedData[$cell->x()][$cell->y()] = $cell->isAlive();
            }
        }

        $center = [round(($maxX + $minX) / 2), round(($maxY + $minY) / 2)];
        $leftTop = [round($center[0] - $width / 2), round($center[1] + $height / 2)];
        $rightBottom = [$leftTop[0] + $width, $leftTop[1] - $height];

        echo '▓▓ - alive ░░ - dead'.PHP_EOL;

        for ($y = $leftTop[1]; $y > $rightBottom[1]; $y--) {
            for ($x = $leftTop[0]; $x < $rightBottom[0]; $x++) {
                echo isset($preparedData[$x][$y]) ? '▓▓ ' : '░░ ';
            }
            echo PHP_EOL;
        }

        echo PHP_EOL;
    }
}


// Init base services
$repository = new CellRepository();
$cellFactory = new CellFactory($repository);

$game = new GameOfLife($cellFactory, $repository);

//$game->initWithRandomPattern(10); // uncomment to use random pattern
//$game->initWith3CellLinePattern(); // uncomment to use 3-cell line pattern
$game->initWithGliderPattern();

$game->printCurrentGen();

// run till gen is alive
while ($game->isGenerationAlive()) {
    sleep(1);
    $game->nextGen();
    $game->printCurrentGen();
}

echo 'GAME OVER! Generation is dead. Total success generations: '.$game->currentGeneration().PHP_EOL;
