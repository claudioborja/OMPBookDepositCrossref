# OMPBookDepositCrossref

Spanish version: README.md

Import/export plugin for **Open Monograph Press (OMP) 3.4.x and 3.5.x** that generates valid **Crossref 5.4.0** XML for registering book and chapter DOIs.

Developed by **Claudio Borja** at **[SOFTECAPPS S.A.S.](https://softecsa.com)**.

Contact: claudio-borja@hotmail.com, info@softecsa.com

---

## Features

- Generates XML ready to be deposited directly to Crossref using schema 5.4.0.
- Supports **monographs** (`book_metadata`) and **book series** (`book_series_metadata`) with automatic detection based on the series field in OMP.
- Automatic `book_type`: `monograph` or `edited_book` depending on the work type configured in OMP.
- Chapter `content_item` output ordered according to the XSD: `contributors -> titles -> abstract -> component_number -> publication_date -> pages -> doi_data`.
- Exports per chapter: title, subtitle, JATS abstract with `xml:lang`, pages, publication date, and component number.
- Book and chapter contributors with: given name, surname, ORCID, and institutional affiliation.
- Automatic `contributor_role`: `editor` for edited volumes, `author` for monographs.
- ISBNs from publication formats (print and electronic).
- Series metadata: title, ISSN, and volume from `seriesPosition`.
- JATS abstracts generated from database content with HTML stripped.

## Reference Schemas

The plugin was developed and validated using these official Crossref example files as references:

- `book5.4.0.xml`: https://gitlab.com/crossref/schema/-/blob/master/best-practice-examples/book5.4.0.xml
- `book_series5.3.0.xml`: https://gitlab.com/crossref/schema/-/blob/master/best-practice-examples/book_series5.3.0.xml
- Official examples collection: https://gitlab.com/crossref/schema/-/tree/master/best-practice-examples

The plugin generates **Crossref 5.4.0** XML, using these official examples as practical guidance to cover both monograph and series-based book deposits.

---

## Installation

1. Copy the `OMPBookDepositCrossref` folder into:
   ```
   plugins/importexport/
   ```
2. In OMP, go to **Settings -> Plugins -> Installed Plugins** and enable **"OMP Book Deposit Crossref"**, or go directly to **Tools -> Import/Export**.

> No additional dependencies or database migrations are required.

---

## Usage

1. Log in as a press manager or administrator.
2. Go to **Tools -> Import/Export -> OMP Book Deposit Crossref**.
3. Select one or more published books.
4. Click **Export XML**.
5. The plugin will download a file named `crossref_books_<timestamp>.xml`, ready to upload to [Crossref Admin](https://doi.crossref.org/).

---

## Requirements

| Requirement | Version |
|---|---|
| OMP | 3.4.x, 3.5.x |
| PHP | 8.1+ |
| Crossref schema | 5.4.0 |

## Verified Compatibility

| OMP | Status |
|---|---|
| 3.4.0.8 | Tested |
| 3.5.0.4 | Tested |

---

## Plugin Structure

```
OMPBookDepositCrossref/
├── OMPBookDepositCrossrefPlugin.php   # Main plugin logic
├── index.php                          # OMP entry point
├── version.xml                        # Plugin version metadata
├── templates/
│   └── index.tpl                      # Smarty user interface
├── README.md                          # Spanish documentation
├── README.en.md                       # English documentation
└── dist/
    └── OMPBookDepositCrossref.tar.gz  # Installable package
```

---

## Known Limitations

- `edition_number` and series DOI are not exported automatically because OMP does not store these fields in the publication/series data model. They can be added manually in the Crossref web form if needed.
- If a book series in OMP has no ISSN configured, the plugin falls back to `book_metadata` instead of `book_series_metadata`.
- The `book_series5.3.0.xml` reference is used as structural guidance for series handling, but the final output is deposited in Crossref 5.4.0 format.

---

## License

This plugin is distributed under the **GNU General Public License v3.0**, the same license used by Open Monograph Press (OMP).

This means you may use, modify, and redistribute the plugin freely, provided that any derivative version is also distributed under GPL v3.

Full license text: https://www.gnu.org/licenses/gpl-3.0.html
