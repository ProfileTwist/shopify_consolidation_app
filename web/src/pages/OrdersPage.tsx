import { useState } from "react"
import { keepPreviousData, useQuery } from "@tanstack/react-query"
import { api } from "@/lib/api"
import type { Connection, Paginated, SalesOrder } from "@/lib/types"
import { Input } from "@/components/ui/input"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
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
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { StatCards } from "@/components/StatCards"

function money(amount: string, currency: string) {
  return new Intl.NumberFormat("en-US", { style: "currency", currency }).format(Number(amount))
}

function statusVariant(status: string | null): "default" | "secondary" | "destructive" | "outline" {
  switch (status) {
    case "paid":
    case "fulfilled":
    case "Activated":
      return "default"
    case "refunded":
    case "partially_refunded":
      return "destructive"
    default:
      return "secondary"
  }
}

const ALL = "all"

export default function OrdersPage() {
  const [search, setSearch] = useState("")
  const [status, setStatus] = useState(ALL)
  const [financial, setFinancial] = useState(ALL)
  const [connectionId, setConnectionId] = useState(ALL)
  const [page, setPage] = useState(1)

  const { data: connections } = useQuery({
    queryKey: ["connections"],
    queryFn: async () => (await api.get<{ data: Connection[] }>("/connections")).data.data,
  })

  const { data, isFetching } = useQuery({
    queryKey: ["orders", { search, status, financial, connectionId, page }],
    queryFn: async () => {
      const params: Record<string, string | number> = { page, per_page: 15 }
      if (search) params.search = search
      if (status !== ALL) params.status = status
      if (financial !== ALL) params.financial_status = financial
      if (connectionId !== ALL) params.connection_id = connectionId
      return (await api.get<Paginated<SalesOrder>>("/orders", { params })).data
    },
    placeholderData: keepPreviousData,
  })

  const orders = data?.data ?? []
  const meta = data?.meta

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold">Consolidated Orders</h1>
        <p className="text-sm text-muted-foreground">
          Orders pulled from all connected Shopify stores into one view.
        </p>
      </div>

      <StatCards />

      <Card>
        <CardHeader>
          <CardTitle>Orders</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
      <div className="flex flex-wrap gap-3">
        <Input
          placeholder="Search order #, account, email…"
          value={search}
          onChange={(e) => {
            setSearch(e.target.value)
            setPage(1)
          }}
          className="max-w-xs"
        />
        <Select value={connectionId} onValueChange={(v) => { setConnectionId(v); setPage(1) }}>
          <SelectTrigger className="w-[200px]"><SelectValue placeholder="Store" /></SelectTrigger>
          <SelectContent>
            <SelectItem value={ALL}>All stores</SelectItem>
            {connections?.map((c) => (
              <SelectItem key={c.id} value={String(c.id)}>{c.name}</SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Select value={status} onValueChange={(v) => { setStatus(v); setPage(1) }}>
          <SelectTrigger className="w-[160px]"><SelectValue placeholder="Status" /></SelectTrigger>
          <SelectContent>
            <SelectItem value={ALL}>Any status</SelectItem>
            <SelectItem value="open">Open</SelectItem>
            <SelectItem value="closed">Closed</SelectItem>
            <SelectItem value="cancelled">Cancelled</SelectItem>
          </SelectContent>
        </Select>
        <Select value={financial} onValueChange={(v) => { setFinancial(v); setPage(1) }}>
          <SelectTrigger className="w-[180px]"><SelectValue placeholder="Financial" /></SelectTrigger>
          <SelectContent>
            <SelectItem value={ALL}>Any financial</SelectItem>
            <SelectItem value="paid">Paid</SelectItem>
            <SelectItem value="pending">Pending</SelectItem>
            <SelectItem value="partially_refunded">Partially refunded</SelectItem>
            <SelectItem value="refunded">Refunded</SelectItem>
            <SelectItem value="voided">Voided</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div className="rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Order #</TableHead>
              <TableHead>Store</TableHead>
              <TableHead>Account</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Financial</TableHead>
              <TableHead>Fulfillment</TableHead>
              <TableHead className="text-right">Total</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {orders.length === 0 && (
              <TableRow>
                <TableCell colSpan={7} className="py-10 text-center text-muted-foreground">
                  {isFetching ? "Loading…" : "No orders found."}
                </TableCell>
              </TableRow>
            )}
            {orders.map((o) => (
              <TableRow key={o.id}>
                <TableCell className="font-medium">{o.order_number}</TableCell>
                <TableCell>{o.connection.name}</TableCell>
                <TableCell>{o.account_name}</TableCell>
                <TableCell><Badge variant={statusVariant(o.status)}>{o.status}</Badge></TableCell>
                <TableCell><Badge variant={statusVariant(o.financial_status)}>{o.financial_status}</Badge></TableCell>
                <TableCell className="text-muted-foreground">{o.fulfillment_status}</TableCell>
                <TableCell className="text-right">{money(o.total_amount, o.currency)}</TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      {meta && (
        <div className="flex items-center justify-between text-sm text-muted-foreground">
          <span>{meta.total} orders · page {meta.current_page} of {meta.last_page}</span>
          <div className="flex gap-2">
            <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
              Previous
            </Button>
            <Button
              variant="outline"
              size="sm"
              disabled={!!meta && page >= meta.last_page}
              onClick={() => setPage((p) => p + 1)}
            >
              Next
            </Button>
          </div>
        </div>
      )}
        </CardContent>
      </Card>
    </div>
  )
}
