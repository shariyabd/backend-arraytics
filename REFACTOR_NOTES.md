# Refactor Notes — Code-Review Task (`Car` / `TeslaCar`)

The assignment's PHP snippet has one functional bug (it prints no name at all) and a cluster of design problems that make it untestable and hard to extend. Below: every defect with *why it matters*, then the corrected code. The runnable version lives at [refactor/Car.php](refactor/Car.php) — `php refactor/Car.php`.

## Original snippet

```php
<?php
class Car {
  public $name;
  function __construct() {
  }
  function get_name() {
    return $this->name;
  }
  function print_assembly() {
    echo "The Tesla Car finishes assembly every Friday at 5pm.";
  }
}
class TeslaCar extends Car {
  function generate_assembly_reports() {
    echo "Generating assembly reports...";
    echo "Exporting CSV format reports...";
    echo "Printing reports...";
  }
}
$car = new TeslaCar("Model_3");
echo $car->get_name();
echo "<br>";
$car->generate_assembly_reports();
?>
```

## Defects found

| # | Defect | Why it matters | Fix |
|---|--------|----------------|-----|
| 1 | **Constructor ignores its argument** — `new TeslaCar("Model_3")` passes a name, but `__construct()` takes no parameters and never assigns `$name` | This is the functional bug: `get_name()` returns `null`, so the program silently prints nothing where the name should be. Silent data loss is the worst kind of bug — nothing errors, the output is just wrong | Constructor promotion: `public function __construct(private string $name) {}` — the argument is required, typed, and stored in one line |
| 2 | **Base class knows about one subclass** — generic `Car` hardcodes "The Tesla Car…" in `print_assembly()` | Inverted dependency: every future subclass (`FordCar`, …) inherits Tesla's text, and Tesla changes force edits to the shared base. A base class must stay ignorant of its children (Liskov-friendly layering) | Message derives from the instance's own data: `sprintf('The %s finishes assembly…', $this->name)`. Brand-specific behavior, if ever needed, belongs in the subclass as an override |
| 3 | **One method, three jobs** — `generate_assembly_reports()` generates, exports to CSV, *and* prints | SRP: three reasons to change welded together. You cannot reuse or test the CSV export without also "printing", and any change risks the other two behaviors | Split into three focused methods (`generateReports()`, `exportCsvReports()`, `printReports()`), orchestrated by one public method |
| 4 | **`echo` inside domain methods** | Side effects couple the classes to one output channel (stdout/HTML). They can't be unit-tested without output buffering, can't feed a logger/API/queue | Methods **return** strings; only the caller at the bottom of the script echoes. Domain logic produces data, presentation renders it |
| 5 | **No visibility, no types, public property** — bare `function`, untyped params/returns, `public $name` | Untyped code moves errors from compile-time to runtime; a public property lets any code mutate state and makes later invariants (validation, formatting) breaking changes | `public`/`private` on every member, `string` parameter and return types, `$name` private behind `getName()`, `declare(strict_types=1)` |
| 6 | **snake_case method names** (`get_name`, `print_assembly`) | PSR-12/PSR-1 mandate camelCase methods; mixed styles make a codebase feel like it has no owner. Names should also reveal intent | `getName()`, `assemblySchedule()` (it *returns* a schedule — it no longer "prints"), `generateAssemblyReports()` |
| 7 | **Dead empty constructor** — `__construct() {}` does nothing | Noise that suggests unfinished work; an empty zero-parameter constructor is pure clutter | The constructor now has a real job (accepting the name). Had it none, it should simply be deleted |
| 8 | **HTML baked into logic** — `echo "<br>"` | The script hardcodes one rendering context (a browser). Run under CLI it prints literal `<br>`. Presentation belongs at the edge, chosen by the caller | `PHP_EOL` in the demo section; the classes themselves emit no markup at all |
| 9 | **Closing `?>` tag in a pure-PHP file** | Anything after `?>` (even a newline) is sent as output — the classic "headers already sent" bug. PSR-12 requires omitting it | Removed; added `declare(strict_types=1)` at the top instead |

## Corrected code

```php
<?php

declare(strict_types=1);

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
```

Output when run:

```
Model_3
The Model_3 finishes assembly every Friday at 5pm.
Generating assembly reports...
Exporting CSV format reports...
Printing reports...
```

## What I deliberately did *not* do

- **No interfaces, abstract classes, or a `ReportGenerator` hierarchy.** For a 20-line exercise with one report consumer, extracting collaborators would be speculative abstraction. The seam is prepared — the three private methods are the natural extraction points the day reporting grows real behavior (files, formats, destinations).
- **No framework or package wiring.** The snippet is a standalone exercise; pulling it into the Laravel app would misrepresent its purpose.
- **Kept both classes in one file** (PSR-1 wants one class per file) so the deliverable stays a single runnable snippet, mirroring the original's shape. In application code they would be split and autoloaded.
- **Kept `TeslaCar extends Car`.** Inheritance is arguably overkill for one subclass, but the exercise is about *fixing* the hierarchy, not deleting it — and after fix #2 the base class is now a legitimate generalization.
