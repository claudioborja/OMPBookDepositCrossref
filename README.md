# OMPBookDepositCrossref

English version: README.en.md

Plugin de importación/exportación para **Open Monograph Press (OMP) 3.4.x y 3.5.x** que genera XML válido en el esquema **Crossref 5.4.0** para registrar DOIs de libros y capítulos.

Desarrollado por **Claudio Borja** en **[SOFTECAPPS S.A.S.](https://softecsa.com)**.

Contacto: claudio-borja@hotmail.com, info@softecsa.com

---

## Características

- Genera XML listo para depositar directamente en Crossref (esquema 5.4.0).
- Soporta **libros monográficos** (`book_metadata`) y **libros de serie** (`book_series_metadata`) con detección automática según el campo de serie del libro en OMP.
- `book_type` automático: `monograph` o `edited_book` según el tipo de trabajo configurado en OMP.
- Capítulos con `content_item` ordenados conforme al XSD: `contributors → titles → abstract → component_number → publication_date → pages → doi_data`.
- Exporta por capítulo: título, subtítulo, abstract JATS con `xml:lang`, páginas, fecha de publicación, número de componente.
- Contribuyentes (libro y capítulo) con: nombre, apellido, ORCID, afiliación institucional.
- `contributor_role` automático: `editor` para volúmenes editados, `author` para monografías.
- ISBNs desde los formatos de publicación (impreso y electrónico).
- Metadata de serie: título, ISSN (impreso/electrónico), volumen desde `seriesPosition`.
- Resumen JATS desde la base de datos con stripeo de HTML.

## Esquemas de referencia

Durante el desarrollo y validación del plugin se usaron como referencia estos ejemplos oficiales de Crossref:

- `book5.4.0.xml`: https://gitlab.com/crossref/schema/-/blob/master/best-practice-examples/book5.4.0.xml
- `book_series5.3.0.xml`: https://gitlab.com/crossref/schema/-/blob/master/best-practice-examples/book_series5.3.0.xml
- Colección oficial de ejemplos: https://gitlab.com/crossref/schema/-/tree/master/best-practice-examples

El plugin genera XML en **Crossref 5.4.0**, pero toma como guía práctica estos ejemplos oficiales para cubrir correctamente los casos de monografías y libros pertenecientes a series.

---

## Instalación

1. Copiar la carpeta `OMPBookDepositCrossref` dentro de:
   ```
   plugins/importexport/
   ```
2. Ir a **OMP → Configuración → Plugins → Plugins instalados** y activar **"OMP Book Deposit Crossref"**, o bien ir directamente a **Herramientas → Importar/Exportar**.

> No requiere instalar dependencias adicionales ni migraciones de base de datos.

---

## Uso

1. Ingresar como administrador/a o gestor/a de la prensa.
2. Ir a **Herramientas → Importar/Exportar → OMP Book Deposit Crossref**.
3. Seleccionar uno o más libros publicados.
4. Hacer clic en **Exportar XML**.
5. Se descargará el archivo `crossref_books_<timestamp>.xml` listo para subir al [Crossref Admin](https://doi.crossref.org/).

---

## Requisitos

| Requisito | Versión |
|---|---|
| OMP | 3.4.x, 3.5.x |
| PHP | 8.1+ |
| Crossref schema | 5.4.0 |

## Compatibilidad verificada

| OMP | Estado |
|---|---|
| 3.4.0.8 | ✅ Probado |
| 3.5.0.4 | ✅ Probado |

---

## Estructura del plugin

```
OMPBookDepositCrossref/
├── OMPBookDepositCrossrefPlugin.php   # Lógica principal del plugin
├── index.php                       # Punto de entrada OMP
├── version.xml                     # Metadatos de versión del plugin
├── templates/
│   └── index.tpl                   # Interfaz de usuario (Smarty)
└── README.md
```

---

## Limitaciones conocidas

- `edition_number` y DOI de la serie no se exportan automáticamente (OMP no almacena estos campos en el modelo de publicación/serie). Se pueden agregar manualmente en el webform de Crossref.
- Si la serie del libro no tiene ISSN configurado en OMP, el plugin usa `book_metadata` en lugar de `book_series_metadata`.
- La referencia `book_series5.3.0.xml` se usa como guía estructural para series, pero la salida final del plugin se deposita en formato Crossref 5.4.0.

---

## Licencia

Este plugin se distribuye bajo la **GNU General Public License v3.0**, la misma licencia que usa Open Monograph Press (OMP).

Esto significa que puedes usar, modificar y redistribuir este plugin libremente, siempre que cualquier versión derivada se distribuya bajo la misma licencia GPL v3.

Ver texto completo: https://www.gnu.org/licenses/gpl-3.0.html
