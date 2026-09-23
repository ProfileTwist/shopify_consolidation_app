import { useQuery } from "@tanstack/react-query"
import { ShoppingCart, DollarSign, Wallet, Store } from "lucide-react"
import { api } from "@/lib/api"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Skeleton } from "@/components/ui/skeleton"

interface Stats {
  orders_count: number
  orders_total: number
  payouts_count: number
  payouts_net: number
  stores_count: number
  stores_healthy: number
}

function money(v: number) {
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "USD",
    maximumFractionDigits: 0,
  }).format(v)
}

export function StatCards() {
  const { data, isLoading } = useQuery({
    queryKey: ["stats"],
    queryFn: async () => (await api.get<Stats>("/stats")).data,
  })

  const cards = [
    { label: "Total orders", value: data ? data.orders_count.toLocaleString() : "", icon: ShoppingCart },
    { label: "Order value", value: data ? money(data.orders_total) : "", icon: DollarSign },
    { label: "Payouts (net)", value: data ? money(data.payouts_net) : "", sub: data ? `${data.payouts_count} payouts` : "", icon: Wallet },
    { label: "Connected stores", value: data ? String(data.stores_count) : "", sub: data ? `${data.stores_healthy} healthy` : "", icon: Store },
  ]

  return (
    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      {cards.map((c) => (
        <Card key={c.label}>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">{c.label}</CardTitle>
            <c.icon className="size-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            {isLoading ? (
              <Skeleton className="h-8 w-24" />
            ) : (
              <>
                <div className="text-2xl font-semibold">{c.value}</div>
                {c.sub && <p className="text-xs text-muted-foreground">{c.sub}</p>}
              </>
            )}
          </CardContent>
        </Card>
      ))}
    </div>
  )
}
