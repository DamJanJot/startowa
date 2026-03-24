# Server Hub

Zaawansowany panel zarządzania serwerem i plikami z wbudowanym terminalem, edytorem kodu i narzędziami developerskimi.

## Funkcjonalności

### 📁 Eksplorator Plików

- Przeglądanie struktury katalogów z poziomu ustalonych granic bezpieczeństwa
- Podgląd zawartości plików tekstowych, obrazów, PDF i Markdown
- Operacje na plikach: tworzenie, edycja, usuwanie, przenoszenie, zmiana nazwy
- Upload indywidualnych plików i całych folderów (z obsługą ZIP)
- Mini widok podglądowy pliku `index.php/html` w każdym folderze

### ⌨️ Terminal

- Wbudowany terminal pływający w interfejsie
- Dedykowana strona terminala w osobnej karcie (URL: `terminal.php`)
- Obsługa poleceń systemowych z białą listą (npm, composer, php, git, ls, dir, etc.)
- Fallback dla hostingów z zablokowanym `shell_exec/proc_open` — wbudowane komendy (pwd, ls, dir, date, whoami, echo)
- Zmiana rozmiaru: przyciski preset'ów (S/M/L) lub ręczne przeciąganie
- Zapamiętywanie pozycji i rozmiaru w localStorage

### 📝 Edytor Kodu

- Edycja plików tekstowych w modalnym oknie
- Kolorowanie składni dla popularne rozszerzenia (PHP, JS, HTML, CSS, Markdown)
- Podgląd kodu z kolorowaniem i eksport do nowej karty
- Live preview dla stron HTML/PHP w przeglądarce

### 🔍 Wyszukiwanie i Zamiana

- **Szukaj w plikach**: inline'owe pole wyszukiwania z lupy (🔍)
- **Znajdź i zamień**: multi-file replace z opcją dry-run (podgląd zmian bez zapisu)
- Filtrowanie po rozszerzeniach plików
- Limit skanowania plików z regulacją

### 🚀 Narzędzia Developerskie

#### Quick Scaffolding

- **Utwórz component**: React/Vue/PHP komponenty
- **Utwórz page**: React/PHP strony
- **Utwórz controller**: Laravel/PHP kontrolery
- Automatyczne szablony z bieżącą strukturą kodu

#### Task Runner

- Definiowanie i uruchamianie własnych tasków (Build, Test, Deploy, etc.)
- Przechowywanie w localStorage
- Import/export tasków jako JSON
- Prawa klik na tasku → usunięcie

### 👤 Panel Użytkownika

- Wyświetlanie nazwy, e-maila i roli użytkownika
- Przycisk zębatki do ustawień (rozwijalne funkcjonalności)
- Wylogowanie

### 📌 Funkcje UX

- Przypisywanie ("przypinanie") folderów do szybkiego dostępu
- Historia ostatnio otwieranych plików
- Zwijanie sidebara do kompaktowego widoku
- Nawigacja z klawiatur (Alt+←, `` ` `` toggle terminal, Escape zamyka modale)

## Struktura Projektu

```
startowa/
├── public/
│   ├── index.php         ← Dashboard i główny interfejs
│   ├── api.php           ← Backend API dla operacji FS/terminal
│   ├── terminal.php      ← Dedykowana strona terminala
│   ├── login.php         ← Autentykacja użytkownika
│   └── logout.php        ← Wylogowanie
├── core/                 ← Współdzielone funkcje (sesje, połączenia)
├── assets/               ← Style, obrazy, komponenty
├── index.php             ← Redirect do public/login.php
├── LICENSE               ← MIT License
└── README.md             ← Ta dokumentacja
```

## Instalacja & Setup

1. **Wymagania**: PHP 7.4+, serwer WWW (Apache, Nginx)

2. **Konfiguracja**
   - Skopiuj projekt do katalogów serwera
   - Dostosuj zmienne sesji w `public/login.php` (username, email, role)
   - Upewnij się, że katalogi mają odpowiednie uprawnienia do odczytu/zapisu

3. **Dostęp**
   - Wejdź na `http://[domain]/index.php`
   - Zostaniesz przekierowany do `public/login.php`
   - Po zalogowaniu przejdziesz do dashboardu

## Bezpieczeństwo

- Operacje plików ograniczone do wyznaczonego roota (zmienna `$serverRoot`)
- Normalizacja ścieżek chroni przed path traversal
- Białe listy poleceń terminala
- Limity rozmiarów skanowania i przetwarzania
- Sesje PHP z weryfikacją logowania

## Role i dostępy aplikacji (RBAC)

Projekt ma zintegrowany model dostępu oparty o role i przypisania aplikacji:

- centralna logika: `core/access_control.php`
- panel użytkownika po loginie: `public/index.php`
- panel admina (Server Hub): `public/admin/index.php`
- zarządzanie rolami/dostępami: `public/admin/access.php`
- linki demo do portfolio i GitHub: `public/admin/demos.php`

### Wdrożenie RBAC na CBA

1. Zaimportuj SQL: `database/add_access_control_tables.sql`.
2. Zaloguj się jako `admin` lub `owner`.
3. W panelu admina kliknij `Dostepy` i przypisz:
   - aplikacje do ról,
   - aplikacje bezpośrednio do użytkowników.

Jeśli użytkownik nie ma przypisanej aplikacji, nie zobaczy jej na dashboardzie. API panelu plików (`public/api.php`) również jest blokowane dla kont bez dostępu `admin_panel` lub `server_hub`.

## Linki demo aplikacji

Panel `public/admin/demos.php` pozwala tworzyć publiczne linki demo, które:

- działają z domeny startowej, np. `.../demo.php?demo=twoj-klucz`,
- przekierowują do autologinu wybranej aplikacji,
- potrafią automatycznie wygenerować token autologinu dla kompatybilnej aplikacji,
- mogą zostać wyłączone lub wygaszone,
- zliczają wejścia.

### Jak używać

1. Przygotuj konto demo i przykładowe dane w aplikacji docelowej.
2. Dla kompatybilnych aplikacji wpisz katalog aplikacji, konto demo i docelowy redirect po loginie.
3. Panel sam utworzy wpis w tabeli `autologiny` aplikacji docelowej albo przyjmie gotowy token ręcznie.
4. Skopiuj publiczny link i użyj go w portfolio albo na GitHub.

### SQL dla panelu demo

Jeśli chcesz wdrożyć tabelę ręcznie, zaimportuj:

- `database/add_demo_links_tables.sql`

## API Backend

Plik `public/api.php` obsługuje akcje:

- `root` — pobieranie głównego katalogu
- `list` — listowanie zawartości folderu
- `read` / `write` — czytanie i edycja plików
- `mkdir` / `create_file` — tworzenie katalogów i plików
- `rename` / `move` / `delete` — operacje na plikach
- `upload` — przesyłanie plików (z opcjonalnym unzipem)
- `preview` — podgląd pliku (image, PDF, Markdown)
- `execute` — wykonywanie poleceń terminala
- `search_text` — wyszukiwanie tekstu w plikach
- `find_replace_text` — zamiana tekstu (z dry-run)
- `web_preview_url` — URL do podglądu webowego

## Rozwój

- Projekt jest vanilla PHP + HTML/CSS/JavaScript (bez frameworków frontend'owych)
- localStorage przechowuje: pinny czasowe, ostatnie pliki, taski, pozycję/rozmiar terminala
- Responsywny design dostosowuje się do ekranów mobilnych

## Licencja

MIT License — zobacz plik LICENSE

---

**Ostatnia aktualizacja**: 2026-03-17  
**Wersja**: 1.0 — Terminal Redesign  
**Autor**: DamJanJot
