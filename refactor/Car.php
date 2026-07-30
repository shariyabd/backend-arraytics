<?php

declare(strict_types=1);

/**
 * Corrected version of the code-review snippet (see ../REFACTOR_NOTES.md).
 *
 * Standalone exercise file — intentionally NOT part of the Laravel app.
 * Run it directly: `php refactor/Car.php`
 */
class Car
{
    public function __construct(private string $name) {}

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Generic for every car: the message derives from the instance's own
     * data instead of hardcoding one subclass's brand in the base class.
     */
    public function assemblySchedule(): string
    {
        return sprintf('The %s finishes assembly every Friday at 5pm.', $this->name);
    }
}

class TeslaCar extends Car
{
    /**
     * Orchestrates the three report steps; each step is its own method so it
     * can change or be tested independently. Returns data — the caller
     * decides how (and whether) to render it.
     *
     * @return list<string>
     */
    public function generateAssemblyReports(): array
    {
        return [
            $this->generateReports(),
            $this->exportCsvReports(),
            $this->printReports(),
        ];
    }

    private function generateReports(): string
    {
        return 'Generating assembly reports...';
    }

    private function exportCsvReports(): string
    {
        return 'Exporting CSV format reports...';
    }

    private function printReports(): string
    {
        return 'Printing reports...';
    }
}

// Demo (the only place output happens — presentation stays out of the classes).
$car = new TeslaCar('Model_3');

echo $car->getName().PHP_EOL;
echo $car->assemblySchedule().PHP_EOL;

foreach ($car->generateAssemblyReports() as $line) {
    echo $line.PHP_EOL;
}
