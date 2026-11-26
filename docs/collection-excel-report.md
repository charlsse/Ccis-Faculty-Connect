# Collection Excel reports

Open a collection's **Report & Excel export**, then **Download Excel report**.
This downloads a real `.xlsx` workbook. Previously downloaded CSV files do not acquire formatting; CSV remains available for raw data interchange.

- **Summary:** collection identity, reporting timestamp/timezone, expected, verified collected, outstanding, waived, collection rate and member status breakdown. A4 portrait, one page.
- **Transactions:** all assigned members, amounts, method/reference, status, payment date, Treasurer verification and receipt identifier. A4 landscape, one page wide with additional pages vertically, repeating report headers and page numbers.
- References and dates use explicit text to preserve their display. Amounts and member counts are numeric. User-provided text cannot become Excel formulas.
- In Excel, use **File → Print → Print Entire Workbook** for both sheets. The report is a snapshot at download time.

The exporter uses PHP's existing ZipArchive extension to package OOXML; no new dependency, migration, gateway configuration or change to financial permissions is required. Treasurer and Admin oversight use the existing authorized report endpoint; faculty cannot download collection-wide reports.

Validation: both worksheets imported and visually rendered using synthetic QA data; automated coverage checks totals, long references, formula safety, all-member export beyond pagination, print settings and faculty access denial. Full suite: 136 tests / 1,217 assertions. Physical printer output was not tested.
