import React from "react";
import { Card, CardHeader, CardBody, Container, Row, Col, Table, Badge } from "reactstrap";
import Header from "components/Headers/Header.js";

const Pipelines = () => {
  const [runs, setRuns] = React.useState([]);
  React.useEffect(() => {
    const run = async () => {
      try {
        const res = await fetch("/api/pipelines/runs?limit=20");
        const j = await res.json();
        setRuns(j.items || []);
      } catch (e) {}
    };
    run();
  }, []);
  return (
    <>
      <Header />
      <Container className="mt--7" fluid>
        <Row>
          <Col>
            <Card className="shadow">
              <CardHeader className="border-0">
                <h3 className="mb-0">Motor de Pipelines</h3>
              </CardHeader>
              <CardBody>
                <Table className="align-items-center table-flush" responsive>
                  <thead className="thead-light">
                    <tr>
                      <th>Nome</th>
                      <th>Status</th>
                      <th>Início</th>
                      <th>Fim</th>
                    </tr>
                  </thead>
                  <tbody>
                    {runs.map((r) => (
                      <tr key={r.id}>
                        <td>{r.name}</td>
                        <td><Badge color={r.status === "success" ? "success" : r.status === "running" ? "info" : "warning"}>{r.status}</Badge></td>
                        <td>{new Date(r.started_at).toLocaleString()}</td>
                        <td>{r.finished_at ? new Date(r.finished_at).toLocaleString() : "-"}</td>
                      </tr>
                    ))}
                  </tbody>
                </Table>
              </CardBody>
            </Card>
          </Col>
        </Row>
      </Container>
    </>
  );
};

export default Pipelines;

