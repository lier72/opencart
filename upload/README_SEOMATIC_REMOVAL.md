# SEOMatic Removal Script

This script safely removes the SEOMatic module from your OpenCart installation.

## File

**`remove_seomatic.sh`** - Universal removal script
- Works on both local development and production servers
- **Auto-detects** database credentials from `config.php`
- No manual configuration needed!
- Includes safety checks and detailed logging

## Before Running

**No configuration needed!** The script automatically reads your database settings from `config.php`.

## Usage

### Step 1: Upload to Server (Production Only)
Upload `remove_seomatic.sh` to your OpenCart root directory (where `config.php` is located).

For local development, the script is already in place.

### Step 2: Make Executable
```bash
chmod +x remove_seomatic.sh
```

### Step 3: Run the Script
```bash
./remove_seomatic.sh
```

The script will:
1. Ask for confirmation before proceeding
2. Create a timestamped backup directory
3. Backup all SEOMatic files and database settings
4. Remove all SEOMatic files and directories
5. Clean database entries
6. Verify removal was successful

## What Gets Removed

### Directories (8):
- `catalog/controller/seomatic/`
- `catalog/model/seomatic/`
- `admin/language/english/seomatic/`
- `admin/controller/seomatic/`
- `admin/model/seomatic/`
- `admin/view/stylesheet/seomatic/`
- `admin/view/javascript/seomatic/`
- `system/helper/seomatic/`

### Files (3):
- `admin/language/english/module/seomatic.php`
- `admin/controller/module/seomatic.php`
- `admin/view/image/seomatic-loading-large.gif`

### Database:
- Entries from `ocus_extension` table
- Entries from `ocus_setting` table (keys containing 'seomatic')
- Entries from `ocus_module` table

## Backup

The script creates a timestamped backup directory (e.g., `seomatic_backup_20231208_143022/`) containing:
- All removed files and directories
- Database settings dump (`seomatic_db_settings.txt`)
- SQL restore script (`restore_db_settings.sql`)

**Keep this backup** until you've verified your site works correctly without SEOMatic.

## After Removal

1. **Clear OpenCart Cache**
   - Admin Panel: System → Maintenance → Clear Cache
   - Or manually: Delete contents of `system/storage/cache/`

2. **Clear Modifications** (if using OCMOD/VQMOD)
   - Admin Panel: Extensions → Modifications → Refresh

3. **Test Your Site**
   - Check admin panel loads without errors
   - Check frontend loads correctly
   - Check error logs: `system/storage/logs/error.log`

## Restoring (If Needed)

If something goes wrong and you need to restore SEOMatic:

```bash
# Restore files
cp -r seomatic_backup_YYYYMMDD_HHMMSS/* ./

# Restore database
mysql -u username -p database_name < seomatic_backup_YYYYMMDD_HHMMSS/restore_db_settings.sql
```

## Troubleshooting

### Permission Errors
If you get permission errors:
```bash
# Make sure you own the files
sudo chown -R youruser:yourgroup .

# Or run with sudo (not recommended)
sudo ./remove_seomatic_production.sh
```

### Database Connection Errors
- Verify database credentials in the script
- Test connection manually:
  ```bash
  mysql -u username -p -h hostname database_name
  ```

### Files Still Exist After Running
- Check file permissions
- Check if files are owned by web server user
- May need to run with elevated permissions

## Why Remove SEOMatic?

SEOMatic is an OpenCart 2.x module that:
- Uses outdated VQMod technology
- May cause compatibility issues in OpenCart 3.x
- Shows as "heading_title" in modules list (language file issue)
- Is not actively maintained

## Support

If you encounter issues:
1. Check the backup directory was created
2. Review any error messages from the script
3. Check OpenCart error logs
4. Test database connection manually

## Safety Features

The script includes:
- ✅ Confirmation prompt before deletion
- ✅ Complete backup of all files
- ✅ Database backup with restore script
- ✅ Verification of removal
- ✅ Detailed logging of all actions
- ✅ No modification of core OpenCart files

## License

This script is provided as-is for removing the SEOMatic module from OpenCart installations.
