# RPG Maps – Agent Instructions

Dieses Repo wird mit dem **MyBB Plugin Dev Kit** entwickelt (Repo `pand0rica/mybb-devkit`,
lokal meist als Nachbarverzeichnis `../mybb-devkit/` ausgecheckt). Bevor du hier etwas tust:

1. Lies zuerst das Dev-Kit: **`../mybb-devkit/AGENTS.md`** und halte dich an alle dort
   verlinkten Standards. Das Kit ist die kanonische Quelle für Konventionen, Boilerplate,
   Debugging und Testing.
2. Lies dann in diesem Repo: `PROJECT_CONTEXT.md` (Kontext).

Das Dev-Kit wird **nicht** in dieses Repo kopiert, sondern referenziert. Liegt es nicht vor,
frag danach, statt zu raten.

## Sprache
Antworten Deutsch, Code/Bezeichner Englisch. PHP 7.4 + 8.x. Keine externen Libraries
(auch kein JS-Framework – Vanilla JS). Alle Texte (Begleitdateien, Sprachdateien, Templates,
Settings) geschlechtergerecht: genderneutral, sonst Gender-Sternchen; Englisch neutral
(Kit `standards/conventions.md` §10).

## Phasen (werden einzeln ausgelöst)

| Eingabe | Phase | Folge dieser Kit-Datei |
|---|---|---|
| „umsetzen / implementieren" | Umsetzung | `../mybb-devkit/workflows/2-implement.md` |
| „reviewe das Projekt" | Review | `../mybb-devkit/workflows/3-review.md` |
| „release das Projekt" | Release | `../mybb-devkit/workflows/4-release.md` |

## Die 5 goldenen Regeln (Kurzform, Details im Kit)
Keine Core-Edits · `TABLE_PREFIX` · `$mybb->get_input()` · POST + `verify_post_check()`
(im ACP über `rpgmaps_acp_verify_key()`) · alles mit Präfix `rpgmaps_`.
Ausgabe `htmlspecialchars_uni()`, SQL `escape_string()`/`(int)` – **auch bei
`insert_query()`/`update_query()`, die nicht selbst escapen.**
