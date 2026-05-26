$root = Get-Location
$pattern = '(?s)<!--== LEFT MENU ==-->.*?<div class="sb2-2">'
$replacement = @'
                <!--== LEFT MENU ==-->
                <div class="sb2-13">
                    <ul class="collapsible" data-collapsible="accordion">
                        <li><a href="admin.html" class="menu-active"><i class="fa fa-bar-chart" aria-hidden="true"></i> Dashboard</a>
                        </li>
                        <li><a href="admin-setting.html"><i class="fa fa-cogs" aria-hidden="true"></i> Site Setting</a>
                        </li>
                        <li><a href="javascript:void(0)" class="collapsible-header"><i class="fa fa-building" aria-hidden="true"></i> Properties</a>
                            <div class="collapsible-body left-sub-menu">
                                <ul>
                                    <li><a href="admin-all-properties.html">All Properties</a>
                                    </li>
                                    <li><a href="admin-add-properties.html">Add New Property</a>
                                    </li>
                                    <li><a href="admin-trash-properties.html">Trash Properties</a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                        <li><a href="javascript:void(0)" class="collapsible-header"><i class="fa fa-users" aria-hidden="true"></i> Tenants</a>
                            <div class="collapsible-body left-sub-menu">
                                <ul>
                                    <li><a href="admin-user-all.php">All Tenants</a>
                                    </li>
                                    <li><a href="admin-user-add.php">Add New Tenant</a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                        <li><a href="javascript:void(0)" class="collapsible-header"><i class="fa fa-calendar" aria-hidden="true"></i> Viewings</a>
                            <div class="collapsible-body left-sub-menu">
                                <ul>
                                    <li><a href="admin-event-all.html">All Viewings</a>
                                    </li>
                                    <li><a href="admin-event-add.html">Schedule New Viewing</a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                        <li><a href="javascript:void(0)" class="collapsible-header"><i class="fa fa-wrench" aria-hidden="true"></i> Maintenance</a>
                            <div class="collapsible-body left-sub-menu">
                                <ul>
                                    <li><a href="admin-seminar-all.html">All Maintenance Tasks</a>
                                    </li>
                                    <li><a href="admin-seminar-add.html">Create Maintenance Task</a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                        <li><a href="javascript:void(0)" class="collapsible-header"><i class="fa fa-commenting-o" aria-hidden="true"></i> Enquiry</a>
                            <div class="collapsible-body left-sub-menu">
                                <ul>
                                    <li><a href="admin-all-enquiry.html">All Enquiry</a></li>
                                    <li><a href="admin-Property-enquiry.html">Property Enquiry</a></li>
                                    <li><a href="admin-admission-enquiry.html">Tenant Enquiry</a></li>
                                    <li><a href="admin-seminar-enquiry.html">Maintenance Enquiry</a></li>
                                    <li><a href="admin-event-enquiry.html">Viewing Enquiry</a></li>
                                    <li><a href="admin-common-enquiry.html">Common Enquiry</a></li>
                                </ul>
                            </div>
                        </li>
                        <li><a href="javascript:void(0)" class="collapsible-header"><i class="fa fa-cloud-download" aria-hidden="true"></i> Import & Export</a>
                            <div class="collapsible-body left-sub-menu">
                                <ul>
                                    <li><a href="admin-export-data.html">Export Data</a>
                                    </li>
                                    <li><a href="admin-import-data.html">Import Data</a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                    </ul>
                </div>
'@
$replacement += '            <div class="sb2-2">'
Get-ChildItem -File -Include *.html,*.php | ForEach-Object {
    $path = $_.FullName
    $text = Get-Content -Path $path -Raw -ErrorAction Stop
    if ($text -match '<!--== LEFT MENU ==-->') {
        $new = [regex]::Replace($text, $pattern, $replacement)
        if ($new -ne $text) {
            Set-Content -Path $path -Value $new -Encoding UTF8
            Write-Host "Updated: $path"
        }
    }
}
if (Test-Path "admin-quick-link.html") {
    Remove-Item "admin-quick-link.html" -Force
    Write-Host "Deleted admin-quick-link.html"
}
