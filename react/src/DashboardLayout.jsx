export default function DashboardLayout({ sidebar, header, children, className = '', sidebarClassName = '', mainClassName = '', headerClassName = '', contentClassName = '' }) {
  return <div className={`dashboard-layout ${className}`.trim()}>
    <aside className={`dashboard-sidebar ${sidebarClassName}`.trim()}>{sidebar}</aside>
    <div className={`dashboard-main ${mainClassName}`.trim()}>
      <header className={`dashboard-header ${headerClassName}`.trim()}>{header}</header>
      <main className={`dashboard-content ${contentClassName}`.trim()}>{children}</main>
    </div>
  </div>;
}
