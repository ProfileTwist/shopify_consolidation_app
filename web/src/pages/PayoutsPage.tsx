import { useState } from "react"
import { keepPreviousData, useQuery } from "@tanstack/react-query"
import { api } from "@/lib/api"
import type { Connection, Paginated, Payout } from "@/lib/types"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"

const ALL = "all"

function money(v: string, currency = "USD") {
  return new Intl.NumberFormat("en-US", { style: "currency", currency }).format(Number(v))
}

function statusVariant(s: string | null): "default" | "secondary" | "destructive" {
  if (s === "paid") return "default"
  if (s === "failed" || s === "canceled") return "destructive"
  return "secondary"
}

export default function PayoutsPage() {
  const [connectionId, setConnectionId] = useState(ALL)
  const [selected, setSelected] = useState<number | null>(null)

  const { data: connections } = useQuery({
    queryKey: ["connections"],
    queryFn: async () => (await api.get<{ data: Connection[] }>("/connections")).data.data,
  })

  const { data } = useQuery({
    queryKey: ["payouts", { connectionId }],
    queryFn: async () => {
      const params: Record<string, string | number> = { per_page: 25 }
      if (connectionId !== ALL) params.connection_id = connectionId
      return (await api.get<Paginated<Payout>>("/payouts", { params })).data
    },
    placeholderData: keepPreviousData,
  })

  // Detail (transaction breakdown) for the selected payout.
  const { data: detail } = useQuery({
    queryKey: ["payout", selected],
    queryFn: async () => (await api.get<{ data: Payout }>(`/payouts/${selected}`)).data.data,
    enabled: selected !== null,
  })

  const payouts = data?.data ?? []

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold">Payouts &amp; Reconciliation</h1>
        <p className="text-sm text-muted-foreground">
          Shopify Payments payouts across all stores, each reconciled to the orders it covers.
        </p>
      </div>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0">
          <CardTitle>Payouts</CardTitle>
          <Select value={connectionId} onValueChange={(v) => { setConnectionId(v); setSelected(null) }}>
            <SelectTrigger className="w-[240px]"><SelectValue placeholder="Store" /></SelectTrigger>
            <SelectContent>
              <SelectItem value={ALL}>All stores</SelectItem>
              {connections?.filter((c) => c.payments_enabled).map((c) => (
                <SelectItem key={c.id} value={String(c.id)}>{c.name}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </CardHeader>
        <CardContent>
      <div className="rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Payout</TableHead>
              <TableHead>Store</TableHead>
              <TableHead>Issued</TableHead>
              <TableHead>Status</TableHead>
              <TableHead className="text-right">Gross</TableHead>
              <TableHead className="text-right">Fees</TableHead>
              <TableHead className="text-right">Refunds</TableHead>
              <TableHead className="text-right">Net</TableHead>
              <TableHead className="text-right">Lines</TableHead>
              <TableHead></TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {payouts.length === 0 && (
              <TableRow>
                <TableCell colSpan={10} className="py-10 text-center text-muted-foreground">
                  No payouts yet — run a sync on a Shopify Payments store.
                </TableCell>
              </TableRow>
            )}
            {payouts.map((p) => (
              <TableRow key={p.id} className={selected === p.id ? "bg-muted/50" : ""}>
                <TableCell className="font-mono text-xs">{p.external_id}</TableCell>
                <TableCell>{p.connection.name}</TableCell>
                <TableCell>{p.issued_at}</TableCell>
                <TableCell><Badge variant={statusVariant(p.status)}>{p.status}</Badge></TableCell>
                <TableCell className="text-right">{money(p.gross)}</TableCell>
                <TableCell className="text-right text-muted-foreground">-{money(p.fees)}</TableCell>
                <TableCell className="text-right text-muted-foreground">-{money(p.refunds)}</TableCell>
                <TableCell className="text-right font-medium">{money(p.amount)}</TableCell>
                <TableCell className="text-right">{p.transactions_count}</TableCell>
                <TableCell className="text-right">
                  <Button
                    size="sm"
                    variant={selected === p.id ? "secondary" : "outline"}
                    onClick={() => setSelected(selected === p.id ? null : p.id)}
                  >
                    {selected === p.id ? "Hide" : "Reconcile"}
                  </Button>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
        </CardContent>
      </Card>

      {selected !== null && detail && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">
              Reconciliation for {detail.external_id} — net {money(detail.amount)}
            </CardTitle>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Transaction</TableHead>
                  <TableHead>Type</TableHead>
                  <TableHead>Order</TableHead>
                  <TableHead className="text-right">Amount</TableHead>
                  <TableHead className="text-right">Fee</TableHead>
                  <TableHead className="text-right">Net</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {detail.transactions?.map((t) => (
                  <TableRow key={t.id}>
                    <TableCell className="font-mono text-xs">{t.external_id}</TableCell>
                    <TableCell>
                      <Badge variant={t.type === "refund" ? "destructive" : "secondary"}>{t.type}</Badge>
                    </TableCell>
                    <TableCell>
                      {t.order?.order_number ?? (
                        <span className="text-muted-foreground">unmatched</span>
                      )}
                    </TableCell>
                    <TableCell className="text-right">{money(t.amount)}</TableCell>
                    <TableCell className="text-right text-muted-foreground">{money(t.fee)}</TableCell>
                    <TableCell className="text-right font-medium">{money(t.net)}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      )}
    </div>
  )
}
