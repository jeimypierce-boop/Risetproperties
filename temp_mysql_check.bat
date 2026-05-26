@echo off
"C:\xampp\mysql\bin\mysql.exe" -u root -e "use riset_properties; show tables like 'maintenance_requests'; show tables like 'tenant_documents'; show tables like 'rent_payments';"
