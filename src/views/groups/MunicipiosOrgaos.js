import React from "react";
import { useLocation } from "react-router-dom";
import { Card, CardHeader, CardBody, Container, Row, Col, Table, Form, Input, InputGroup, InputGroupAddon, InputGroupText, Button } from "reactstrap";
import Header from "components/Headers/Header.js";

const MunicipiosOrgaos = () => {
  const [orgaos, setOrgaos] = React.useState([]);
  const [latest, setLatest] = React.useState([]);
  const [metrics, setMetrics] = React.useState(null);
  const [q, setQ] = React.useState("");
  const [uf, setUf] = React.useState("");
  const [tipo, setTipo] = React.useState("");
  const [status, setStatus] = React.useState("");
  const [limit, setLimit] = React.useState(20);
  const [offset, setOffset] = React.useState(0);
  const ufList = ["","AC","AL","AP","AM","BA","CE","DF","ES","GO","MA","MT","MS","MG","PA","PB","PR","PE","PI","RJ","RN","RS","RO","RR","SC","SP","SE","TO"];
  const fetchItems = async (reset=false) => {
    try {
      const params = new URLSearchParams();
      params.set("limit", String(limit));
      params.set("offset", String(reset?0:offset));
      if (q) params.set("q", q);
      if (uf) params.set("uf", uf);
      if (tipo) params.set("tipo", tipo);
      if (status) params.set("status", status);
      const res = await fetch(`/api/orgaos/search?${params.toString()}`);
      const j = await res.json();
      setOrgaos(j.items || []);
      if (reset) setOffset(0);
    } catch (e) {}
  };
  const location = useLocation();
  React.useEffect(() => {
    const run = async () => {
      try { const r = await fetch('/api/metrics/overview'); const j = await r.json(); setMetrics(j); } catch(e){}
      try { const r2 = await fetch('/api/municipios/count'); const j2 = await r2.json(); setMetrics((m)=>({...(m||{}), municipios:j2.count||0})); } catch(e){}
      try { const r3 = await fetch('/api/orgaos/list?limit=5'); const j3 = await r3.json(); setLatest(j3.items||[]); } catch(e){}
      const p = location.pathname || "";
      if (p.includes('/orgaos/prefeituras')) { setTipo('prefeitura'); }
      else if (p.includes('/orgaos/camaras')) { setTipo('camara_municipal'); }
      else if (p.includes('/orgaos/secretarias-municipais')) { setTipo(''); setQ('Secretaria de '); }
      else if (p.includes('/orgaos/secretarias-estaduais')) { setQ('Secretaria de Estado'); }
      else if (p.includes('/orgaos/tribunais')) { setQ('Tribunal '); }
      else if (p.includes('/orgaos/ministerios')) { setQ('Ministério '); }
      fetchItems(true);
    };
    run();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [location.pathname]);
  return (
    <>
      <Header />
      <Container className="mt--7" fluid>
        {metrics && (
          <Row className="mb-4">
            <Col lg="3" md="6"><div className="card card-stats"><div className="card-body"><div className="numbers"><p className="card-category">Prefeituras</p><h5 className="card-title">{metrics.prefeituras}</h5></div></div></div></Col>
            <Col lg="3" md="6"><div className="card card-stats"><div className="card-body"><div className="numbers"><p className="card-category">Municípios</p><h5 className="card-title">{metrics.municipios||0}</h5></div></div></div></Col>
            <Col lg="3" md="6"><div className="card card-stats"><div className="card-body"><div className="numbers"><p className="card-category">Órgãos</p><h5 className="card-title">{metrics.orgaos}</h5></div></div></div></Col>
          </Row>
        )}
        <Row>
          <Col>
            <Card className="shadow">
              <CardHeader className="border-0">
                <h3 className="mb-0">Municípios & Órgãos</h3>
              </CardHeader>
              <CardBody>
                {latest.length>0 && (
                  <div className="mb-4">
                    <h5>Últimos órgãos</h5>
                    <Table className="align-items-center table-flush" responsive>
                      <thead className="thead-light">
                        <tr><th>Tipo</th><th>Nome</th><th>UF</th><th>Domínio</th></tr>
                      </thead>
                      <tbody>
                        {latest.map((o)=>(<tr key={o.id}><td>{o.tipo}</td><td>{o.nome}</td><td>{o.uf}</td><td>{o.dominio||"-"}</td></tr>))}
                      </tbody>
                    </Table>
                  </div>
                )}
                <Form inline className="mb-3" onSubmit={(e)=>{e.preventDefault(); fetchItems(true);}}>
                  <Row className="w-100">
                    <Col md="4" className="mb-2">
                      <InputGroup>
                        <InputGroupAddon addonType="prepend">
                          <InputGroupText>
                            <i className="fa fa-search" />
                          </InputGroupText>
                        </InputGroupAddon>
                        <Input placeholder="Buscar por nome" value={q} onChange={(e)=>setQ(e.target.value)} />
                      </InputGroup>
                    </Col>
                    <Col md="2" className="mb-2">
                      <Input type="select" value={uf} onChange={(e)=>setUf(e.target.value)}>
                        {ufList.map((u)=>(<option key={u} value={u}>{u || "UF"}</option>))}
                      </Input>
                    </Col>
                    <Col md="3" className="mb-2">
                      <Input placeholder="Tipo" value={tipo} onChange={(e)=>setTipo(e.target.value)} />
                    </Col>
                    <Col md="2" className="mb-2">
                      <Input placeholder="Status" value={status} onChange={(e)=>setStatus(e.target.value)} />
                    </Col>
                    <Col md="1" className="mb-2 text-right">
                      <Button color="primary" onClick={()=>fetchItems(true)}>Filtrar</Button>
                    </Col>
                  </Row>
                </Form>
                <Table className="align-items-center table-flush" responsive>
                  <thead className="thead-light">
                    <tr>
                      <th>Tipo</th>
                      <th>Nome</th>
                      <th>UF</th>
                      <th>Domínio</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    {orgaos.map((o) => (
                      <tr key={o.id}>
                        <td>{o.tipo}</td>
                        <td>{o.nome}</td>
                        <td>{o.uf}</td>
                        <td>{o.dominio || "-"}</td>
                        <td>{o.status}</td>
                      </tr>
                    ))}
                  </tbody>
                </Table>
                <div className="d-flex justify-content-between align-items-center mt-3">
                  <div>
                    <Button color="secondary" onClick={()=>{ if(offset>=limit){ setOffset(offset-limit); fetchItems(); } }} disabled={offset===0}>Anterior</Button>{" "}
                    <Button color="secondary" onClick={()=>{ setOffset(offset+limit); fetchItems(); }}>Próximo</Button>
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

export default MunicipiosOrgaos;
