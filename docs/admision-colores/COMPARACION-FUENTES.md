# Comparación de fuentes verificadas en Git

SHA | CSS SHA-256 | Reglas de fondo completo
--- | --- | ---
1f840e90912d2ce8c55ce7502a22a1d582492bc3 | fcc7cefd38f03f0fb3814a18f9fdc3ae0e70e49cd47c32496727fc08296768a6 | AUSENTES
d245918793014621066c8f35822dfca51b9ec296 | c1cd148e54077d2c90681a1255f08f62913647bffeeeac8c28c543f012390c79 | PRESENTES
3e1ca22b8e7e07bb2ddf18221cd8ff70499242a7 | c1cd148e54077d2c90681a1255f08f62913647bffeeeac8c28c543f012390c79 | PRESENTES
f29019a1f3649149a31af3447e67e009970a7277 | c1cd148e54077d2c90681a1255f08f62913647bffeeeac8c28c543f012390c79 | PRESENTES

El CSS de d245918, del padre inmediato 3e1ca22 y del merge f29019a es idéntico byte a byte. No hay un cambio de paleta CSS atribuible al merge #65. La prueba de navegador combina deliberadamente CSS histórico 1f840e9 con JavaScript integrado para reproducir el aspecto sin fondo; no asegura que esa sea la combinación servida por Hostinger sin verificar los hashes HTTP reales.
