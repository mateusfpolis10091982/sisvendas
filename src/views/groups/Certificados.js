import React from "react";
import { Card, CardHeader, CardBody, Container, Row, Col, Table, Badge, Form, Input, InputGroup, InputGroupAddon, InputGroupText, Button } from "reactstrap";
import Header from "components/Headers/Header.js";

const Certificados = () => {
  const [items, setItems] = React.useState([]);
  const [loading, setLoading] = React.useState(false);
  const [q, setQ] = React.useState("");
  const [uf, setUf] = React.useState("");
  const [limit, setLimit] = React.useState(20);
  const [offset, setOffset] = React.useState(0);
  const [metrics, setMetrics] = React.useState(null);
  const [summary, setSummary] = React.useState(null);
  const [daysMax, setDaysMax] = React.useState(90);
  const [sslStatus, setSslStatus] = React.useState("");
  const ufList = ["","AC","AL","AP","AM","BA","CE","DF","ES","GO","MA","MT","MS","MG","PA","PB","PR","PE","PI","RJ","RN","RS","RO","RR","SC","SP","SE","TO"];
  const statusColor = (s) => {
    const v = (s||"").toLowerCase();
    if (v.includes("valid")) return "success";
    if (v.includes("expir")) return "warning";
    if (v.includes("expirado") || v.includes("expired")) return "danger";
    return "info";
  };
  const fetchMetrics = async () => {
    try { const r = await fetch("/api/metrics/overview"); const j = await r.json(); setMetrics(j); } catch (e) {}
  };
  const fetchItems = async (reset = false) => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      params.set("limit", String(limit));
      params.set("offset", String(reset ? 0 : offset));
      params.set("days_max", String(daysMax));
      if (q) params.set("q", q);
      if (uf) params.set("uf", uf);
      if (sslStatus) params.set("status", sslStatus);
      const res = await fetch(`/api/ssl/expiring?${params.toString()}`);
      const j = await res.json();
      setItems(j.items || []);
      if (reset) setOffset(0);
    } catch (e) {}
    setLoading(false);
  };
  React.useEffect(() => {
    fetchMetrics();
    (async ()=>{ try { const r = await fetch('/api/ssl/summary'); const j = await r.json(); setSummary(j); } catch(e){} })();
    fetchItems(true);
  }, []);
  return (
    <>
      <Header />
      <Container className="mt--7" fluid>
        {metrics && (
          <Row className="mb-4">
            <Col lg="3" md="6">
              <Card className="card-stats">
                <CardBody>
                  <div className="numbers">
                    <p className="card-category">Prefeituras</p>
                    <h5 className="card-title">{metrics.prefeituras}</h5>
                  </div>
                </CardBody>
              </Card>
            </Col>
            <Col lg="3" md="6">
              <Card className="card-stats">
                <CardBody>
                  <div className="numbers">
                    <p className="card-category">Órgãos</p>
                    <h5 className="card-title">{metrics.orgaos}</h5>
                  </div>
                </CardBody>
              </Card>
            </Col>
            <Col lg="3" md="6">
              <Card className="card-stats">
                <CardBody>
                  <div className="numbers">
                    <p className="card-category">SSL Scans</p>
                    <h5 className="card-title">{metrics.ssl_scans}</h5>
                  </div>
                </CardBody>
              </Card>
            </Col>
            <Col lg="3" md="6">
              <Card className="card-stats">
                <CardBody>
                  <div className="numbers">
                    <p className="card-category">Oportunidades</p>
                    <h5 className="card-title">{metrics.oportunidades}</h5>
                  </div>
                </CardBody>
              </Card>
            </Col>
          </Row>
        )}
        <Row>
          <Col>
            <Card className="shadow">
              <CardHeader className="border-0">
                <h3 className="mb-0">Certificados & Rastreamento</h3>
              </CardHeader>
              <CardBody>
                <Form inline className="mb-3" onSubmit={(e)=>{e.preventDefault(); fetchItems(true);}}>
                  <Row className="w-100">
                    <Col md="6" className="mb-2">
                      <InputGroup>
                        <InputGroupAddon addonType="prepend">
                          <InputGroupText>
                            <i className="fa fa-search" />
                          </InputGroupText>
                        </InputGroupAddon>
                        <Input placeholder="Buscar por nome" value={q} onChange={(e)=>setQ(e.target.value)} />
                      </InputGroup>
                    </Col>
                    <Col md="3" className="mb-2">
                      <Input type="select" value={uf} onChange={(e)=>setUf(e.target.value)}>
                        {ufList.map((u)=>(<option key={u} value={u}>{u || "UF"}</option>))}
                      </Input>
                    </Col>
                    <Col md="2" className="mb-2">
                      <Input type="select" value={sslStatus} onChange={(e)=>setSslStatus(e.target.value)}>
                        {['','valid','expired','unknown'].map(s=>(<option key={s} value={s}>{s || 'Status'}</option>))}
                      </Input>
                    </Col>
                    <Col md="2" className="mb-2">
                      <Input type="select" value={daysMax} onChange={(e)=>setDaysMax(parseInt(e.target.value||'90',10))}>
                        {[7,15,30,60,90].map(n=>(<option key={n} value={n}>{`≤ ${n} dias`}</option>))}
                      </Input>
                    </Col>
                    <Col md="2" className="mb-2 text-right">
                      <Button color="primary" onClick={()=>fetchItems(true)} disabled={loading}>Filtrar</Button>
                    </Col>
                  </Row>
                </Form>
                {summary && (
                  <Row className="mb-4">
                    <Col lg="2" md="4"><div className="card card-stats"><div className="card-body"><div className="numbers"><p className="card-category">SSL Total</p><h5 className="card-title">{summary.total}</h5></div></div></div></Col>
                    <Col lg="2" md="4"><div className="card card-stats"><div className="card-body"><div className="numbers"><p className="card-category">Expirado</p><h5 className="card-title">{summary.expired}</h5></div></div></div></Col>
                    <Col lg="2" md="4"><div className="card card-stats"><div className="card-body"><div className="numbers"><p className="card-category">≤15 dias</p><h5 className="card-title">{summary.lte15}</h5></div></div></div></Col>
                    <Col lg="2" md="4"><div className="card card-stats"><div className="card-body"><div className="numbers"><p className="card-category">≤30 dias</p><h5 className="card-title">{summary.lte30}</h5></div></div></div></Col>
                    <Col lg="2" md="4"><div className="card card-stats"><div className="card-body"><div className="numbers"><p className="card-category">≤90 dias</p><h5 className="card-title">{summary.lte90}</h5></div></div></div></Col>
                  </Row>
                )}
                <Table className="align-items-center table-flush" responsive>
                  <thead className="thead-light">
                    <tr>
                      <th>Órgão</th>
                      <th>UF</th>
                      <th>Domínio</th>
                      <th>Issuer</th>
                      <th>SSL</th>
                      <th>Validade</th>
                      <th>Dias</th>
                      <th>Ações</th>
                    </tr>
                  </thead>
                  <tbody>
                    {!loading && items.map((r) => (
                      <tr key={r.orgao_id || r.dominio}>
                        <td>{r.nome || '-'}</td>
                        <td>{r.uf}</td>
                        <td>{r.dominio || "-"}</td>
                        <td>{r.issuer || '-'}</td>
                        <td>
                          {r.status ? (<Badge color={statusColor(r.status)}>{r.status}</Badge>) : ("-")}
                        </td>
                        <td>{r.valid_to ? new Date(r.valid_to).toLocaleDateString() : "-"}</td>
                        <td>{typeof r.dias_restantes === 'number' ? r.dias_restantes : '-'}</td>
                        <td>
                          <Button size="sm" color="info" onClick={async()=>{ if(!r.orgao_id) return; try { const u = `/api/ia/analyze-orgao?id=${r.orgao_id}`; const res = await fetch(u); await res.json(); } catch(e){} }}>Analisar</Button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </Table>
                <div className="d-flex justify-content-between align-items-center mt-3">
                  <div>
                    <Button color="secondary" onClick={()=>{ if(offset>=limit){ setOffset(offset-limit); fetchItems(); } }} disabled={offset===0 || loading}>Anterior</Button>{" "}
                    <Button color="secondary" onClick={()=>{ setOffset(offset+limit); fetchItems(); }} disabled={loading}>Próximo</Button>
                  </div>
                  <div>
                    <Input type="select" value={limit} onChange={(e)=>{ setLimit(parseInt(e.target.value||"20",10)); fetchItems(true); }} style={{width:120}}>
                      {[10,20,50].map(n=>(<option key={n} value={n}>{n}/página</option>))}
                    </Input>
                  </div>
                </div>
              </CardBody>
            </Card>
          </Col>
        </Row>
      </Container>
    </>
  );
};

export default Certificados;
