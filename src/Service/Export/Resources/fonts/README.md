# Schriftdaten für den PDF-Export

Der PDF-Export der SE-KulturTage (`SekEventPdfExporter`) setzt wie das frühere
`pdfEvents.php` die Schrift DejaVu Sans (normal und fett). `tecnickcom/tc-lib-pdf`
braucht dafür umgewandelte Schriftdaten (`*.json`, `*.z`, `*.ctg.z`). Die
Bibliothek bringt keine mit; ihr eigenes Erzeugungsskript lädt die Quellen beim
`composer install` aus dem Internet und wandelt alle Schriftfamilien um. Damit der
Export ohne Netzzugriff und ohne zusätzlichen Schritt im Deployment funktioniert,
liegen die zwei benötigten Schnitte hier im Bundle.

- Quelle: DejaVu Sans 2.37 (`DejaVuSans.ttf`, `DejaVuSans-Bold.ttf`, Debian-Paket
  `fonts-dejavu-core` 2.37-8 im PHP-Image der Website)
- Lizenz: Bitstream Vera, DejaVu-Änderungen gemeinfrei (`LICENSE-DejaVu.txt`);
  die Glyphen sind unverändert, nur das Datenformat ist umgewandelt.
- Umwandlung mit `tecnickcom/tc-lib-pdf-font` (Version laut `composer.lock` der Website):

```bash
php vendor/tecnickcom/tc-lib-pdf-font/util/convert.php \
    --outpath=<dieser Ordner> --type=TrueTypeUnicode --flags=32 \
    --fonts=/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf,/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf
```

Nach einem Major-Update von `tc-lib-pdf-font` die Dateien mit diesem Befehl neu
erzeugen und den PDF-Export prüfen.
