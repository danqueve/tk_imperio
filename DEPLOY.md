# Deploy — TK Imperio (`tk.imperiocomercial.com.ar`)

Pasos para publicar en el VPS. Marcá cada uno al terminarlo. Ejemplos con el proyecto en `public_html/tk/`; ajustar la ruta si es otra.

## 1. Código

```bash
cd ~/public_html
git clone https://github.com/danqueve/tk_imperio.git tk
cd tk
mkdir -p uploads && chmod 775 uploads
```

Las actualizaciones posteriores son `git pull` dentro de `tk/`.

## 2. Subdominio y raíz web

El `.htaccess` de la raíz del repo ya hace tres cosas para el host `tk.imperiocomercial.com.ar`: fuerza HTTPS, sirve solo `public/` y bloquea `config/`, `src/`, `sql/`, `scripts/`, `uploads/` y `.git`.

Hay dos formas de usarlo, según cómo estén armados los otros subdominios:

- **Subdominio con raíz propia:** apuntar el document root de `tk.imperiocomercial.com.ar` a la carpeta `tk/`. No hace falta nada más.
- **Subdominios que comparten `public_html`** (como los otros): agregar al `.htaccess` de `public_html`, junto a las reglas de los otros subdominios:

```apache
RewriteCond %{HTTP_HOST} ^tk\.imperiocomercial\.com\.ar$ [NC]
RewriteRule ^(?!tk/)(.*)$ tk/$1 [L]
```

El `.htaccess` de `tk/` toma el resto (HTTPS y `public/`). Con esto el sitio queda en la raíz del dominio: `base_url` vacío.

## 3. Base de datos

```bash
mysql -u root -p < sql/schema.sql          # crea tk_imperio con sus tablas
```

Usuario propio de TK, solo con permisos sobre su base:

```sql
CREATE USER 'tk_app'@'localhost' IDENTIFIED BY '********';
GRANT SELECT, INSERT, UPDATE, DELETE ON tk_imperio.* TO 'tk_app'@'localhost';
```

## 4. Usuario de solo lectura sobre SAS

```sql
CREATE USER 'tk_sas_ro'@'localhost' IDENTIFIED BY '********';
GRANT SELECT ON c2881399_credit.ic_usuarios TO 'tk_sas_ro'@'localhost';
GRANT SELECT ON c2881399_credit.ic_clientes TO 'tk_sas_ro'@'localhost';
FLUSH PRIVILEGES;
```

Si el MySQL es 8.4 o superior, crear el usuario sin `mysql_native_password` (ya no existe; usar el plugin por defecto `caching_sha2_password`).

## 5. Configuración de producción

Copiar `config/config.local.example.php` a `config/config.local.php` (no va a git) y completar:

```php
'app' => [
    'debug'         => false,
    'base_url'      => '',
    'cookie_secure' => true,
],
```

con las credenciales de `tk_app` y de `tk_sas_ro`.

## 6. Verificar

```bash
php scripts/verificar_deploy.php
```

Tiene que terminar en "Todo en orden". Revisa PHP, extensiones, `uploads/`, la base, `cookie_secure`/`debug`, y que el usuario de SAS tenga solo `SELECT`.

También confirmar en el `php.ini` del VPS: `upload_max_filesize` y `post_max_size` de al menos 8M.

## 7. Primer ingreso

- Los administradores entran con su usuario y contraseña de SAS Imperio.
- No crear usuarios de prueba en producción. Si SAS no está disponible, admin local de emergencia: `php scripts/crear_admin_local.php <usuario> <clave> "<Nombre>"`.
- Crear los supervisores y usuarios en **Usuarios**.

## 8. Pantalla de pared

Con sesión de admin, entrar a **Pantalla**, copiar la dirección (`https://tk.imperiocomercial.com.ar/pantalla/?t=...`) y abrirla en el navegador de la TV. Con HTTPS, la pantalla evita que la TV entre en reposo. Si la dirección se filtra, usar **Regenerar token**.

## 9. Backup

`scripts/backup_tk.sh` deja la base y `uploads/` comprimidas en una carpeta. Las credenciales salen de `~/.my.cnf` (`[client]` con usuario y contraseña, permisos 600).

```bash
# crontab -e   (diario, antes de que corra el script de rclone)
30 2 * * * sh ~/public_html/tk/scripts/backup_tk.sh ~/backups/tk
```

Y en el script diario de rclone que ya existe, sumar la carpeta `~/backups/tk`.

## 10. Prueba final

- [ ] Entra un admin de SAS y un usuario común.
- [ ] La búsqueda por DNI completa el cliente desde SAS.
- [ ] Se cargan 3 fotos desde un celular real.
- [ ] Se toma, reasigna y resuelve un ticket; desaparece de la pantalla en la próxima actualización.
- [ ] La TV muestra las columnas, se actualiza sola y vuelve tras cortar internet un momento.
- [ ] Por URL directa, `/config/config.php`, `/uploads/...` y `/PLAN.md` dan 403.
