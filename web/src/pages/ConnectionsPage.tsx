import { useEffect } from "react"
import { useSearchParams } from "react-router-dom"
import { useForm } from "react-hook-form"
import { zodResolver } from "@hookform/resolvers/zod"
import { z } from "zod"
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { toast } from "sonner"
import { api } from "@/lib/api"
import { useAuth } from "@/lib/auth"
import type { Connection } from "@/lib/types"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Badge } from "@/components/ui/badge"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"

const schema = z.object({
  shop_domain: z.string().min(1, "Shop domain is required"),
  name: z.string().optional(),
  access_token: z.string().optional(),
  sync_interval_seconds: z.coerce.number().int().min(5).max(86400),
})
type FormValues = z.infer<typeof schema>

function statusVariant(status: string): "default" | "secondary" | "destructive" {
  if (status === "healthy") return "default"
  if (status === "error") return "destructive"
  return "secondary"
}

export default function ConnectionsPage() {
  const { can } = useAuth()
  const qc = useQueryClient()
  const canManage = can("manage connections")

  const { data: connections } = useQuery({
    queryKey: ["connections"],
    queryFn: async () => (await api.get<{ data: Connection[] }>("/connections")).data.data,
  })

  const { register, handleSubmit, reset, formState: { errors, isSubmitting } } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { shop_domain: "", name: "", access_token: "", sync_interval_seconds: 60 },
  })

  // Handle the return from the Shopify OAuth redirect (?connected=1|0).
  const [searchParams, setSearchParams] = useSearchParams()
  useEffect(() => {
    const connected = searchParams.get("connected")
    if (!connected) return
    if (connected === "1") {
      toast.success(`Connected ${searchParams.get("shop") ?? "store"}`)
      qc.invalidateQueries({ queryKey: ["connections"] })
      qc.invalidateQueries({ queryKey: ["stats"] })
    } else {
      toast.error(`Connect failed: ${searchParams.get("error") ?? "unknown error"}`)
    }
    setSearchParams({}, { replace: true })
  }, [searchParams, setSearchParams, qc])

  const connectMutation = useMutation({
    mutationFn: (values: FormValues) => api.post<{ mode: string; url?: string; message?: string }>("/shopify/connect", values),
    onSuccess: (res) => {
      // Live OAuth: hand off to Shopify. Demo: store is created immediately.
      if (res.data.mode === "oauth" && res.data.url) {
        window.location.href = res.data.url
        return
      }
      toast.success(res.data.message ?? "Store connected")
      reset()
      qc.invalidateQueries({ queryKey: ["connections"] })
      qc.invalidateQueries({ queryKey: ["stats"] })
    },
    onError: (err) => {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
      toast.error(msg ?? "Could not connect store")
    },
  })

  const syncMutation = useMutation({
    mutationFn: (id: number) => api.post(`/connections/${id}/sync`),
    onSuccess: () => {
      toast.success("Sync dispatched — orders will refresh shortly")
      qc.invalidateQueries({ queryKey: ["connections"] })
    },
    onError: () => toast.error("Sync failed to dispatch"),
  })

  const toggleMutation = useMutation({
    mutationFn: (c: Connection) => api.put(`/connections/${c.id}`, { is_active: !c.is_active }),
    onSuccess: () => {
      toast.success("Connection updated")
      qc.invalidateQueries({ queryKey: ["connections"] })
    },
    onError: () => toast.error("Update failed"),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/connections/${id}`),
    onSuccess: () => {
      toast.success("Connection removed")
      qc.invalidateQueries({ queryKey: ["connections"] })
    },
    onError: () => toast.error("Delete failed"),
  })

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold">Store Connections</h1>
        <p className="text-sm text-muted-foreground">
          Each connected Shopify store and its sync health.
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Connected stores</CardTitle>
        </CardHeader>
        <CardContent>
      <div className="rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Store</TableHead>
              <TableHead>Payments</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Orders</TableHead>
              <TableHead>Payouts</TableHead>
              <TableHead>Every</TableHead>
              <TableHead>Last synced</TableHead>
              {canManage && <TableHead className="text-right">Actions</TableHead>}
            </TableRow>
          </TableHeader>
          <TableBody>
            {connections?.map((c) => (
              <TableRow key={c.id}>
                <TableCell>
                  <div className="font-medium">{c.name}</div>
                  {c.shop_domain && (
                    <div className="text-xs text-muted-foreground">{c.shop_domain}</div>
                  )}
                </TableCell>
                <TableCell>
                  {c.payments_enabled ? (
                    <Badge variant="outline">Shopify Payments</Badge>
                  ) : (
                    <span className="text-xs text-muted-foreground">3rd-party processor</span>
                  )}
                </TableCell>
                <TableCell>
                  <div className="flex items-center gap-2">
                    <Badge variant={statusVariant(c.status)}>{c.status}</Badge>
                    {!c.is_active && <Badge variant="outline">paused</Badge>}
                  </div>
                </TableCell>
                <TableCell>{c.orders_count ?? 0}</TableCell>
                <TableCell>{c.payments_enabled ? (c.payouts_count ?? 0) : "—"}</TableCell>
                <TableCell className="text-muted-foreground">{c.sync_interval_seconds}s</TableCell>
                <TableCell className="text-muted-foreground">
                  {c.last_synced_at ? new Date(c.last_synced_at).toLocaleString() : "never"}
                </TableCell>
                {canManage && (
                  <TableCell className="text-right">
                    <div className="flex justify-end gap-2">
                      <Button
                        size="sm"
                        variant="outline"
                        disabled={syncMutation.isPending}
                        onClick={() => syncMutation.mutate(c.id)}
                      >
                        Sync now
                      </Button>
                      <Button
                        size="sm"
                        variant="ghost"
                        disabled={toggleMutation.isPending}
                        onClick={() => toggleMutation.mutate(c)}
                      >
                        {c.is_active ? "Pause" : "Resume"}
                      </Button>
                      <Button
                        size="sm"
                        variant="ghost"
                        className="text-destructive"
                        disabled={deleteMutation.isPending}
                        onClick={() => {
                          if (confirm(`Remove "${c.name}"? This deletes its orders and payouts.`)) {
                            deleteMutation.mutate(c.id)
                          }
                        }}
                      >
                        Delete
                      </Button>
                    </div>
                  </TableCell>
                )}
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
        </CardContent>
      </Card>

      {canManage && (
        <Card className="max-w-xl">
          <CardHeader>
            <CardTitle>Connect a Shopify store</CardTitle>
          </CardHeader>
          <CardContent>
            <form
              onSubmit={handleSubmit((v) => connectMutation.mutate(v))}
              className="space-y-4"
            >
              <div className="space-y-2">
                <Label htmlFor="shop_domain">Shop domain</Label>
                <Input
                  id="shop_domain"
                  placeholder="mystore.myshopify.com"
                  {...register("shop_domain")}
                />
                {errors.shop_domain && (
                  <p className="text-sm text-destructive">{errors.shop_domain.message}</p>
                )}
                <p className="text-xs text-muted-foreground">
                  Just the store's <code>.myshopify.com</code> domain — you'll approve access on Shopify.
                </p>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="name">Display name (optional)</Label>
                  <Input id="name" placeholder="Mr Daisy - Midtown" {...register("name")} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="sync_interval_seconds">Sync every (seconds)</Label>
                  <Input
                    id="sync_interval_seconds"
                    type="number"
                    min={5}
                    {...register("sync_interval_seconds")}
                  />
                </div>
              </div>
              <div className="space-y-2">
                <Label htmlFor="access_token">Admin API access token (optional)</Label>
                <Input
                  id="access_token"
                  type="password"
                  placeholder="shpat_…"
                  autoComplete="off"
                  {...register("access_token")}
                />
                <p className="text-xs text-muted-foreground">
                  Paste a store's custom-app token to connect it directly. Leave blank to use OAuth/demo.
                </p>
              </div>
              <Button type="submit" disabled={isSubmitting || connectMutation.isPending}>
                {connectMutation.isPending ? "Connecting…" : "Connect store"}
              </Button>
            </form>
          </CardContent>
        </Card>
      )}
    </div>
  )
}
