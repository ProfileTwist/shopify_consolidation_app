import { Outlet, useLocation } from "react-router-dom"
import { AppSidebar } from "@/components/AppSidebar"
import { SidebarInset, SidebarProvider, SidebarTrigger } from "@/components/ui/sidebar"
import { Separator } from "@/components/ui/separator"

const titles: Record<string, string> = {
  "/": "Orders",
  "/payouts": "Payouts",
  "/products": "Products",
  "/connections": "Connections",
  "/users": "Users",
}

export default function Layout() {
  const location = useLocation()
  const title = titles[location.pathname] ?? "Dashboard"

  return (
    <SidebarProvider>
      <AppSidebar />
      <SidebarInset>
        <header className="flex h-16 shrink-0 items-center gap-2 border-b px-4">
          <SidebarTrigger className="-ml-1" />
          <Separator orientation="vertical" className="mr-2 h-4" />
          <div className="flex flex-col">
            <span className="text-xs text-muted-foreground">Shopify Consolidator</span>
            <span className="text-sm font-medium">{title}</span>
          </div>
        </header>
        <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
          <Outlet />
        </div>
      </SidebarInset>
    </SidebarProvider>
  )
}
