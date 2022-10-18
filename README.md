## Simple tool for importing data from MYSQL tables to other tables with different column names and types.
### Read this instruction before start importing.

Before starting the process:
1) enter ```composer update``` from root directory of project. 
2) fill ```relations.php``` file
   * relations of import/export columns should be defined in ```"columns_relations"``` section.
   * check ```"settings"``` section to configure script execution.
   * check ```"defaults"``` section to set default values that will be used for null values when insert in NOT NULL columns.

###

In case of data type mismatch - script independently transforms the data taken from the donor table (if possible) with setting ```transform_types = true```.

###

If value can't be transformed script will try to insert modified values with ```transform_all = true```. 
##### Example:

|           | patient          | donor             |
|-----------|------------------|-------------------|
| Type      | TINYINT UNSIGNED | SMALLINT UNSIGNED |
| Max value | 127              | 32767             |

In case if donor-value > patient-maxvalue - maximum allowed value will be inserted (127).

For string values (VARCHAR, TEXT etc.) value will be truncated.
###### 
For date values (TIMESTAMP, DATE etc.) value will be defaulted.
###### 
For decimal values (FLOAT, DOUBLE, DECIMAL) value will be defaulted.

#### Tool won't transform values with types:
* BIT
* BINARY
* VARBINARY
* TINYBLOB
* BLOB
* MEDIUMBLOB
* LONGBLOB
* GEOMETRY
* POINT
* LINESTRING
* POLYGON
* GEOMETRYCOLLECTION
* MULTILINESTRING
* MULTIPOINT
* MULTIPOLYGON
* ENUM (with differences)
* SET